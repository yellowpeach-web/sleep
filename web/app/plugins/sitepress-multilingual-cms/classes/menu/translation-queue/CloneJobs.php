<?php

namespace WPML\TM\Menu\TranslationQueue;

use WPML\FP\Either;
use WPML\FP\Obj;
use WPML\TM\API\Job\Map;
use WPML\TM\ATE\NothingToTranslateJob;
use WPML\TM\Jobs\JobLog;
use WPML\TM\Jobs\TranslationRecord;
use WPML_Element_Translation_Job;
use WPML_TM_Editors;
use WPML_TM_ATE_Jobs;
use WPML\TM\ATE\JobRecords;
use WPML_TM_ATE_API;

class CloneJobs {
	const RESULT_ATE_JOB_CREATED = 'ate_job_created';

	const RESULT_COMPLETED_LOCALLY = 'completed_locally';

	const RESULT_FAILED = 'failed';

	private $ateJobs;

	private $apiClient;

	private $repeatInterval;

	private $record;

	public function __construct( WPML_TM_ATE_Jobs $ateJobs, WPML_TM_ATE_API $apiClient, $repeatInterval = 5000000, ?TranslationRecord $record = null ) {
		$this->ateJobs        = $ateJobs;
		$this->apiClient      = $apiClient;
		$this->repeatInterval = $repeatInterval;
		$this->record         = $record ?: new TranslationRecord();
	}

	public function cloneCompletedATEJob( WPML_Element_Translation_Job $jobObject, $sentFrom = null, $hasBeenAlreadyRepeated = false ) {
		$ateJobId = (int) $jobObject->get_basic_data_property('editor_job_id');
		$result   = $this->apiClient->clone_job( $ateJobId, $jobObject, $sentFrom );
		if ( $result ) {
			$newAteJobId = (int) $result['id'];

			if ( TranslationRecord::isCompleted( $jobObject ) ) {
				$request = $this->record->openRequest( $jobObject, $sentFrom );
				if ( ! $request ) {
					return Either::left( $jobObject );
				}
				$jobObject = $request;
				wpml_tm_load_old_jobs_editor()->set( (int) $jobObject->get_id(), WPML_TM_Editors::ATE );
				$jobObject->set_basic_data_property( 'editor', WPML_TM_Editors::ATE );
			}

			$wpmlJobId = (int) $jobObject->get_id();

			$this->ateJobs->store( $wpmlJobId, [ JobRecords::FIELD_ATE_JOB_ID => $newAteJobId ] );
			$jobObject->set_basic_data_property( 'editor_job_id', $newAteJobId );

			do_action( 'wpml_tm_ate_jobs_created', [ $wpmlJobId => $newAteJobId ] );

			return Either::of( $jobObject );
		} elseif ( ! $hasBeenAlreadyRepeated ) {
			usleep( $this->repeatInterval );

			return $this->cloneCompletedATEJob( $jobObject, $sentFrom, true );
		} else {
			return Either::left( $jobObject );
		}
	}

	public function cloneWPMLJob( $wpmlJobId, $sentFrom = null, $ateJobId = 0 ) {
		$model = wpml_tm_create_ATE_job_creation_model( $wpmlJobId );

		if ( ! $model ) {
			return self::RESULT_FAILED;
		}

		if ( NothingToTranslateJob::isJobModel( $model ) ) {
			try {
				NothingToTranslateJob::completeJob( $wpmlJobId );
			} catch ( \Throwable $throwable ) {
				JobLog::addError(
					'CloneJobs: local completion of empty job failed',
					[
						'job_id'  => (int) $wpmlJobId,
						'message' => $throwable->getMessage(),
					]
				);

				return self::RESULT_FAILED;
			}

			return self::RESULT_COMPLETED_LOCALLY;
		}

		$payload = [ 'jobs' => [ $model ] ];
		if ( $sentFrom ) {
			$payload['job_type'] = $sentFrom;
		}

		$params = json_decode( (string) wp_json_encode( $payload ), true );

		$inFlight = \WPML\TM\ATE\JobCreation\AteCreateReconciler::IN_FLIGHT_OPTION_PREFIX . (int) $wpmlJobId;
		$expiry   = time() + \WPML_TM_ATE_Jobs_Actions::IN_FLIGHT_MARKER_TTL;
		wp_cache_delete( $inFlight, 'options' );
		update_option( $inFlight, $expiry, false );
		$ambiguous = true;
		try {
			$response  = $this->apiClient->create_jobs( $params );
			$ambiguous = is_wp_error( $response ) || ( ! is_object( $response ) && ! is_array( $response ) );
		} finally {
			if ( $ambiguous ) {
				$this->markUnconfirmed( (int) $wpmlJobId, (int) $ateJobId );
			}
			wp_cache_delete( $inFlight, 'options' );
			if ( (int) get_option( $inFlight, 0 ) === $expiry ) {
				delete_option( $inFlight );
			}
		}

		if ( ! $ambiguous && Obj::prop( 'jobs', $response ) ) {
			$newAteJobId = Obj::path( [ 'jobs', Map::fromJobId( $wpmlJobId ) ], $response );

			$this->ateJobs->store( $wpmlJobId, [ JobRecords::FIELD_ATE_JOB_ID => $newAteJobId ] );
			wpml_tm_load_old_jobs_editor()->set( $wpmlJobId, WPML_TM_Editors::ATE );
			$this->ateJobs->warm_cache( [ $wpmlJobId ] );

			do_action( 'wpml_tm_ate_jobs_created', [ (int) $wpmlJobId => (int) $newAteJobId ] );

			return self::RESULT_ATE_JOB_CREATED;
		}

		return self::RESULT_FAILED;
	}

	private function markUnconfirmed( $wpmlJobId, $ateJobId ) {
		$job = $this->jobRow( $wpmlJobId );
		if ( $job ? (int) $job->editor_job_id > 0 : $ateJobId > 0 ) {
			return;
		}

		$name = \WPML\TM\ATE\JobCreation\AteCreateReconciler::UNCONFIRMED_OPTION_PREFIX . $wpmlJobId;
		$mark = get_option( $name, false );
		if ( false === $mark ) {
			$mode = '';
			if ( $job ) {
				$mode = WPML_TM_Editors::ATE === $job->editor || (int) $job->automatic ? 'auto' : 'manual';
			}
			$mark = [ 'at' => time(), 'mode' => $mode ];
		} elseif ( ! is_array( $mark ) ) {
			$mark = [ 'at' => $mark ];
		}

		$mark['clone'] = time();
		update_option( $name, $mark, false );
	}

	private function jobRow( $wpmlJobId ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT editor, editor_job_id, automatic FROM {$wpdb->prefix}icl_translate_job WHERE job_id = %d",
				$wpmlJobId
			)
		);
	}
}
