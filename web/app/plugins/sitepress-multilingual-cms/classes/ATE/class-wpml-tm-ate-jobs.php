<?php

use WPML\EditHistory\DeliveryAnswer;
use WPML\EditHistory\DeliveryFactory;
use WPML\EditHistory\InternalWrite;
use WPML\FP\Cast;
use WPML\FP\Maybe;
use WPML\TM\API\Jobs;
use WPML\TM\API\Job\Map;
use WPML\TM\ATE\JobRecords;
use WPML\TM\ATE\API\RequestException;
use WPML\TM\Jobs\JobLog;
use function WPML\FP\pipe;
use function WPML\FP\partialRight;
use WPML\FP\Obj;
use WPML\FP\Logic;
use WPML\FP\Fns;
use function \WPML\FP\invoke;

class WPML_TM_ATE_Jobs {

	const SKIPPED_NOT_AWAITING_DELIVERY = null;

	private $records;

	private static $lastUnsavedReason = '';

	private static $lastUnsavedData = [];

	public function __construct( JobRecords $records ) {
		$this->records = $records;
	}

	public function get_ate_job_id( $wpml_job_id ) {
		$wpml_job_id = (int) $wpml_job_id;

		return $this->records->get_ate_job_id( $wpml_job_id );
	}

	public function get_wpml_job_id( $ate_job_id ) {
		return Maybe::fromNullable( $ate_job_id )
		            ->map( Cast::toInt() )
		            ->map( [ $this->records, 'get_data_from_ate_job_id' ] )
		            ->map( Obj::prop( 'wpml_job_id' ) )
		            ->map( Cast::toInt() )
		            ->getOrElse( null );
	}

	public function store( $wpml_job_id, $ate_job_data ) {
		$this->records->store( (int) $wpml_job_id, $ate_job_data );
	}

	public function apply( $xliff, $expectedJobId = null, $deliveringAteJobId = null, $ateAnswer = null ) {
		\WPML\TM\ATE\TranslateEverything\TaxonomyTermJobApplier::clearLastFailureReason();
		self::$lastUnsavedReason = '';
		self::$lastUnsavedData   = [];

		if ( null !== $expectedJobId ) {
			$requestRow = \WPML\TM\Jobs\TranslationRecord::requestRowOf( (int) $expectedJobId, $deliveringAteJobId );
			if ( $requestRow ) {
				JobLog::add( 'apply_rebound_to_request_row', [
					'record_job_id'  => (int) $expectedJobId,
					'request_job_id' => $requestRow,
					'ate_job_id'     => (int) $deliveringAteJobId,
				] );
				$expectedJobId = $requestRow;
			}
		}

		if ( \WPML\TM\XLIFF\TermBundleXliffReader::isTermBatchXliff( $xliff ) || \WPML\TM\XLIFF\TaxonomyTermXliffReader::isTermXliff( $xliff ) ) {
			global $wpdb, $sitepress;

			return ( new \WPML\TM\ATE\TranslateEverything\TaxonomyTermJobApplier( $wpdb, $sitepress ) )->apply( $xliff, $expectedJobId, $deliveringAteJobId );
		}

		if ( $deliveringAteJobId && ! $this->isHeldByAJob( (int) $deliveringAteJobId ) ) {
			JobLog::add( 'apply_skipped_ate_job_of_no_row', [
				'job_id'     => (int) $expectedJobId,
				'ate_job_id' => (int) $deliveringAteJobId,
			] );
			self::skipped(
				/* translators: %d: the job ID in the Advanced Translation Editor. */
				sprintf( __( 'No job on this site holds ATE job %d any more, so its translation was not applied.', 'sitepress' ), (int) $deliveringAteJobId ),
				'skipped_ate_job_of_no_row'
			);

			return self::SKIPPED_NOT_AWAITING_DELIVERY;
		}

		$factory       = wpml_tm_load_job_factory();
		$xliff_factory = new WPML_TM_Xliff_Reader_Factory( $factory );
		$xliff_reader  = $xliff_factory->general_xliff_reader();
		$delivery      = DeliveryFactory::delivery();
		if ( $delivery ) {
			$xliff_reader->set_carried_history( [ $delivery, 'carriedHistory' ] );
		}
		$job_data = $xliff_reader->get_data( $xliff );

		if ( ! is_wp_error( $job_data )
		     && null !== $expectedJobId
		     && (int) $job_data['job_id'] !== (int) $expectedJobId
		     && $this->isRequestMadeFrom( (int) $expectedJobId, (int) $job_data['job_id'] ) ) {
			JobLog::add( 'apply_bound_to_request_row', [
				'declared_job_id' => (int) $job_data['job_id'],
				'request_job_id'  => (int) $expectedJobId,
			] );

			$job_data = $xliff_reader->get_data_for_job( $xliff, (int) $expectedJobId );
		}

		if ( is_wp_error( $job_data ) && null !== $expectedJobId ) {
			$declaredJobId = $xliff_reader->cleaned_job_declared_for_original_of( $xliff, (int) $expectedJobId );
			if ( $declaredJobId && $this->isRequestMadeFrom( (int) $expectedJobId, $declaredJobId ) ) {
				JobLog::add( 'apply_bound_to_request_row_after_cleanup', [
					'declared_job_id' => $declaredJobId,
					'request_job_id'  => (int) $expectedJobId,
				] );

				$job_data = $xliff_reader->get_data_for_job( $xliff, (int) $expectedJobId );
			}
		}

		if ( is_wp_error( $job_data ) ) {
			$refusalData = $job_data->get_error_data();
			$refusal     = new RequestException(
				$job_data->get_error_message(),
				$job_data->get_error_code(),
				is_array( $refusalData ) && $refusalData ? $refusalData : null
			);
			throw $refusal;
		}

		$answer = DeliveryAnswer::read( $ateAnswer )->withAbsent( (array) ( $job_data['absent_units'] ?? [] ) );
		unset( $job_data['absent_units'] );

		kses_remove_filters();
		$job_data    = $this->filterJobData( $job_data );
		$wpml_job_id = $job_data['job_id'];

		if ( null !== $expectedJobId && (int) $wpml_job_id !== (int) $expectedJobId ) {
			kses_init();

			throw new \Exception(
				sprintf(
					'The delivered XLIFF declares job %d but the delivery was bound to job %d; nothing was applied.',
					(int) $wpml_job_id,
					(int) $expectedJobId
				)
			);
		}

		$blocking_status = $this->getStatusBlockingDelivery( $wpml_job_id );
		if ( null !== $blocking_status ) {
			kses_init();

			JobLog::add(
				'apply_skipped_not_awaiting_delivery',
				[
					'job_id' => (int) $wpml_job_id,
					'status' => $blocking_status,
				]
			);
			self::skipped(
				/* translators: %d: translation job ID. */
				sprintf( __( 'Job %d is not waiting for a translation on this site (it was cancelled or reset), so its translation was not applied.', 'sitepress' ), (int) $wpml_job_id ),
				'skipped_not_awaiting_delivery'
			);

			return self::SKIPPED_NOT_AWAITING_DELIVERY;
		}

		$decision = $delivery ? $delivery->decide( (int) $wpml_job_id, (int) $deliveringAteJobId, $answer ) : null;
		if ( $decision && ! $decision->writes() ) {
			kses_init();

			JobLog::add(
				'edit_history_delivery_skipped',
				[
					'job_id'     => (int) $wpml_job_id,
					'ate_job_id' => $decision->ateJobId,
					'reason'     => $decision->reason,
				]
			);
			self::skipped(
				/* translators: %1$d: translation job ID, %2$s: why the site's edit history kept the translation it has (English). */
				sprintf( __( 'The translation of job %1$d was not applied: %2$s.', 'sitepress' ), (int) $wpml_job_id, (string) $decision->reason ),
				'skipped_by_edit_history'
			);

			return self::SKIPPED_NOT_AWAITING_DELIVERY;
		}

		try {
			$write    = fn() => wpml_tm_save_data( $job_data, false );
			$is_saved = InternalWrite::run( InternalWrite::ATE_DELIVERY, $decision ? fn() => $delivery->apply( $decision, $write ) : $write );
		} catch ( Exception $e ) {
			throw new Exception(
				'The XLIFF file could not be applied to the content of the job ID: ' . $wpml_job_id,
				$e->getCode(),
				$e
			);
		}

		kses_init();

		if ( $is_saved && $decision ) {
			$delivery->record( $decision );
		}

		if ( ! $is_saved ) {
			$unsaved                 = self::unsavedReason( (int) $wpml_job_id );
			self::$lastUnsavedReason = $unsaved['sentence'];
			self::$lastUnsavedData   = $unsaved['data'];
		}

		return $is_saved ? $wpml_job_id : false;
	}

	public static function lastUnsavedReason() {
		return self::$lastUnsavedReason;
	}

	private static function skipped( $sentence, $reasonCode ) {
		self::$lastUnsavedReason = $sentence;
		self::$lastUnsavedData   = [ 'reasonCode' => $reasonCode, 'jobData' => [] ];
	}

	public static function lastUnsavedData() {
		return self::$lastUnsavedData;
	}

	private static function unsavedReason( $jobId ) {
		global $wpdb;

		if ( ! $wpdb instanceof wpdb ) {
			return [ 'sentence' => '', 'data' => [] ];
		}

		return ( new \WPML\TM\XLIFF\MismatchReason( $wpdb ) )->unsavedDetails( $jobId );
	}

	private function getStatusBlockingDelivery( $wpml_job_id ) {
		$status = Jobs::getStatus( (int) $wpml_job_id );

		if ( null === $status ) {
			return null;
		}

		return ICL_TM_NOT_TRANSLATED === $status || Jobs::isCancelled( (int) $wpml_job_id ) ? $status : null;
	}

	private function isHeldByAJob( $ateJobId ) {
		global $wpdb;

		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT job_id FROM {$wpdb->prefix}icl_translate_job WHERE editor_job_id = %d LIMIT 1", $ateJobId ) );
	}

	private function isRequestMadeFrom( $requestJobId, $recordJobId ) {
		if ( $requestJobId <= $recordJobId ) {
			return false;
		}

		$requestRid = (int) Map::ridFromDB( $requestJobId );

		return $requestRid > 0
		       && $requestRid === (int) Map::ridFromDB( $recordJobId )
		       && \WPML\TM\Jobs\TranslationRecord::isCompletedJobId( $recordJobId );
	}

	private function filterJobData( $jobData ) {
		$filteredJobData = apply_filters(
			'wpml_tm_ate_job_data_from_xliff',
			$jobData,
			$this->getJobTargetLanguage()
		);

		if ( array_key_exists( 'job_id', $filteredJobData ) && array_key_exists( 'fields', $filteredJobData ) ) {
			$jobData = $filteredJobData;
		}

		return $jobData;
	}

	private function getJobTargetLanguage() {
		$getJobEntityById = partialRight( [
			wpml_tm_get_jobs_repository(),
			'get_job'
		], \WPML_TM_Job_Entity::POST_TYPE );
		$getTargetLangIfEntityExists = Logic::ifElse( Fns::identity(), invoke( 'get_target_language' ), Fns::always( null ) );

		return pipe( Obj::prop( 'rid' ), $getJobEntityById, $getTargetLangIfEntityExists );
	}

	public function is_editing_job( $wpml_job_id ) {
		return $this->records->is_editing_job( $wpml_job_id );
	}

	public function warm_cache( array $wpml_job_ids ) {
		$this->records->warmCache( $wpml_job_ids );
	}
}
