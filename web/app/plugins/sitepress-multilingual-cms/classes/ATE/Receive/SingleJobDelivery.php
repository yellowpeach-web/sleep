<?php

namespace WPML\TM\ATE\Receive;

use WPML\FP\Obj;
use WPML\TM\API\Jobs;
use WPML\TM\ATE\SyncLock;
use WPML\TM\Jobs\TranslationRecord;

class SingleJobDelivery {

	const APPLIED      = TranslationApplier::APPLIED;
	const NOT_READY    = TranslationApplier::XLIFF_NOT_READY;
	const APPLY_FAILED = TranslationApplier::APPLY_FAILED;
	const LOCK_BUSY    = 'lock_busy';
	const CANCELLED    = 'cancelled';
	const JOB_MISSING  = 'job_missing';
	const CLIENT_EDITS = 'client_edits';
	const SUPERSEDED   = 'superseded';
	const CLOSED       = 'closed_request';

	const WRONG_ORIGINAL = TranslationApplier::WRONG_ORIGINAL;

	const RETRYABLE = [ self::NOT_READY, self::APPLY_FAILED, self::LOCK_BUSY, self::WRONG_ORIGINAL ];

	private $lock;

	private $applier;

	public function __construct( SyncLock $lock, $applier ) {
		$this->lock    = $lock;
		$this->applier = $applier;
	}

	private function applier() {
		if ( ! $this->applier instanceof TranslationApplier ) {
			$this->applier = call_user_func( $this->applier );
		}

		return $this->applier;
	}

	public function deliver( $wpmlJobId, $lockName, ?callable $onLockAcquired = null ) {
		$wpmlJobId = (int) $wpmlJobId;

		if ( ! $this->lock->create( $lockName ) ) {
			return self::outcome( self::LOCK_BUSY );
		}

		try {
			if ( $onLockAcquired ) {
				$onLockAcquired();
			}

			return $this->decide( $wpmlJobId );
		} finally {
			$this->lock->release();
		}
	}

	private function decide( $wpmlJobId ) {
		$wpmlJob = Jobs::get( $wpmlJobId );

		if ( Jobs::isCancelled( $wpmlJobId ) ) {
			return self::outcome( self::CANCELLED );
		}

		$request = TranslationRecord::openRequestWithAteJobOf( $wpmlJobId );
		if ( $request ) {
			return $this->deliverForRequestRow( $wpmlJobId, $wpmlJob, $request );
		}

		if ( ! $wpmlJob && JobKind::isTaxonomyTerm( $wpmlJobId ) ) {
			return self::outcome( $this->applier()->applyForTerm( $wpmlJobId ) );
		}

		if ( ! $wpmlJob ) {
			return self::outcome( self::JOB_MISSING );
		}

		if ( ClientEdits::areInTheWay( $wpmlJob, Obj::prop( 'editor_job_id', $wpmlJob ) ) ) {
			return self::outcome( self::CLIENT_EDITS );
		}

		$supersededBy = $this->findSupersedingJob( $wpmlJobId );

		if ( $supersededBy ) {
			return self::outcome( self::SUPERSEDED, $supersededBy );
		}

		if ( TranslationRecord::isCompletedJobId( $wpmlJobId )
			&& TranslationRecord::closedCloneOf( $wpmlJobId )
			&& ! in_array( Jobs::getStatus( $wpmlJobId ), [ ICL_TM_WAITING_FOR_TRANSLATOR, ICL_TM_IN_PROGRESS ], true ) ) {
			return self::outcome( self::CLOSED );
		}

		return self::outcome(
			$this->applier()->applyForPost(
				Obj::prop( 'job_id', $wpmlJob ),
				Obj::prop( 'original_doc_id', $wpmlJob )
			)
		);
	}

	private function deliverForRequestRow( $recordJobId, $record, array $request ) {
		$requestJobId = (int) $request['job_id'];

		if ( ! $record && JobKind::isTaxonomyTerm( $recordJobId ) ) {
			$outcome = $this->applier()->applyForTerm( $requestJobId );
		} else {
			$requestJob = Jobs::get( $requestJobId );
			$editedJob  = $requestJob ?: $record;
			if ( ClientEdits::areInTheWay( $editedJob, $request['editor_job_id'] ) ) {
				return self::outcome( self::CLIENT_EDITS );
			}

			$outcome = $this->applier()->applyForPost(
				$requestJobId,
				Obj::prop( 'original_doc_id', $record ?: $requestJob )
			);

			if ( TranslationApplier::XLIFF_NOT_READY === $outcome
				&& ClientEdits::areInTheWay( $editedJob, Obj::prop( 'editor_job_id', $record ) ) ) {
				return self::outcome( self::CLIENT_EDITS );
			}
		}

		if ( TranslationApplier::XLIFF_NOT_READY === $outcome ) {
			return self::outcome( self::SUPERSEDED, [ 'rid' => (int) $request['rid'], 'newer_job_id' => $requestJobId ] );
		}

		return self::outcome( $outcome );
	}

	private static function outcome( $code, ?array $supersededBy = null ) {
		return [
			'outcome'       => $code,
			'superseded_by' => $supersededBy,
		];
	}

	public static function isRetryable( $code ) {
		return in_array( $code, self::RETRYABLE, true );
	}

	private function findSupersedingJob( $wpmlJobId ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT j.rid AS rid,
				        ( SELECT MAX( newer.job_id )
				            FROM {$wpdb->prefix}icl_translate_job newer
				           WHERE newer.rid = j.rid ) AS latest_job_id
				   FROM {$wpdb->prefix}icl_translate_job j
				  WHERE j.job_id = %d",
				(int) $wpmlJobId
			)
		);

		if ( ! is_object( $row ) || ! isset( $row->latest_job_id ) ) {
			return null;
		}

		if ( (int) $row->latest_job_id <= (int) $wpmlJobId ) {
			return null;
		}

		return [
			'rid'          => (int) $row->rid,
			'newer_job_id' => (int) $row->latest_job_id,
		];
	}
}
