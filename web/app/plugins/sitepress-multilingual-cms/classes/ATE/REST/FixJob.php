<?php

namespace WPML\TM\ATE\REST;

use WPML\TM\ATE\ReturnedJobs;
use WP_REST_Request;
use WPML\Rest\Adaptor;
use WPML\TM\REST\Base;
use WPML_TM_ATE_AMS_Endpoints;
use \WPML_TM_ATE_API;
use \WPML_TM_ATE_Jobs;
use \WPML_TM_Jobs_Repository;
use WPML\FP\Obj;
use WPML\TM\ATE\API\RequestException;
use WPML\TM\ATE\Log\Entry;
use WPML\TM\ATE\Log\EventsTypes;
use WPML\Core\Security\ExecutionContext\ExecutionContextHolder;
use WPML\TM\Jobs\Authorization\AuthorizedJobResolver;

class FixJob extends Base {

	private $ateJobs;

	private $ateApi;

	private $jobsRepository;

	const PARAM_ATE_JOB_ID = 'ateJobId';
	const PARAM_WPML_JOB_ID = 'jobId';

	public function __construct( Adaptor $adaptor, WPML_TM_ATE_API $ateApi, WPML_TM_ATE_Jobs $ateJobs ) {
		parent::__construct( $adaptor );

		$this->ateApi  = $ateApi;
		$this->ateJobs = $ateJobs;
		$this->jobsRepository = wpml_tm_get_jobs_repository();
	}

	public function get_routes() {
		return [
			[
				'route' => WPML_TM_ATE_AMS_Endpoints::FIX_JOB,
				'args'  => [
					'methods'  => 'GET',
					'callback' => [ $this, 'fix_job' ],
					],
				],
			];
	}

	public function get_allowed_capabilities( WP_REST_Request $request ) {
		return [
			'manage_options',
		    'manage_translations',
		    'translate',
		    ];
	}

	public function fix_job( WP_REST_Request $request ) {
		try {
			$ateJobId = $request->get_param( self::PARAM_ATE_JOB_ID );
			$wpmlJobId = $request->get_param( self::PARAM_WPML_JOB_ID );

			$authorized = ( new AuthorizedJobResolver( $this->ateJobs ) )->byBoundPair(
				ExecutionContextHolder::current(),
				$wpmlJobId,
				$ateJobId
			);
			if ( ! $authorized ) {
				return [ 'completed' => false, 'error' => true ];
			}
			$ateJobId  = $authorized->ateId();
			$wpmlJobId = $authorized->localId();

			$processedJobResult = $this->process( $ateJobId, $wpmlJobId );

			if ( $processedJobResult ) {
				return [ 'completed' => true, 'error' => false ];
			}
		} catch ( \Exception $e ) {
			$this->logException( $e, [ 'ateJobId' => $ateJobId, 'wpmlJobId' => $wpmlJobId ] );
			return [ 'completed' => false, 'error' => true ];
		}
		return [ 'completed' => false, 'error' => false ];
	}

	public function process( $ateJobId, $wpmlJobId ) {
		$response = $this->ateApi->get_job( $ateJobId );

		if ( is_wp_error( $response ) || ! isset( $response->{$ateJobId} ) ) {
			$unknown_job_message = sprintf( 'ATE does not know job %s.', (string) $ateJobId );
			throw new \Exception( esc_html( $unknown_job_message ) );
		}

		$ateJob   = $response->{$ateJobId};
		$xliffUrl = Obj::prop('translated_xliff', $ateJob);

		if ( $xliffUrl ) {
			$xliffContent = $this->ateApi->get_remote_xliff_content( $xliffUrl, [ 'jobId' => $wpmlJobId, 'ateJobId' => $ateJobId ] );
			$receivedWpmlJobId = $this->ateJobs->apply( $xliffContent, (int) $wpmlJobId );

			if ( $receivedWpmlJobId && intval( $receivedWpmlJobId ) !== intval( $wpmlJobId ) ) {
				$error_message = sprintf( 'The received wpmlJobId (%s) does not match (%s).', $receivedWpmlJobId, $wpmlJobId );
				throw new \Exception( $error_message );
			}

			if ( $receivedWpmlJobId ) {
				return true;
			}
		}

		return false;
	}

	private function logException( \Exception $e, $job = null ) {
		$entry              = new Entry();
		$entry->description = $e->getMessage();

		if ( $job ) {
			$entry->ateJobId  = Obj::prop('ateJobId', $job);
			$entry->wpmlJobId = Obj::prop('wpmlJobId', $job);
			$entry->extraData = [ 'downloadUrl' => Obj::prop('url', $job) ];
		}

		if ( $e instanceof RequestException ) {
			$entry->eventType = EventsTypes::SERVER_XLIFF;
		} else {
			$entry->eventType = EventsTypes::JOB_DOWNLOAD;
		}

		wpml_tm_ate_ams_log( $entry );
	}
}
