<?php

namespace WPML\TM\ATE\Handshake;

use WPML\TM\ATE\Receive\SingleJobDelivery;

class ApplyFinished {

	const APPLIED      = 'applied';
	const NOT_COMPLETE = 'not_complete';
	const FALLBACK     = 'fallback';

	const LOG_KIND = 'save_apply';

	const LOCK_WAIT_SECONDS = 10;

	const LOCK_POLL_MICROSECONDS = 250000;

	const LOCK_HOLDER_PREFIX = 'saveApplyFirst.';

	const SKIPPED_WRITTEN_BY_RUN = 'written_by_run';

	const LOG_KIND_CHECK = 'save_check';

	const CHECK_SECONDS = 2;

	const CHECK_MIN_TIMEOUT = 0.1;

	const KEPT_NOT_COMPLETE = 'not_complete';
	const KEPT_NOT_LISTED   = 'not_listed';
	const KEPT_BUDGET       = 'budget';
	const KEPT_OVER_BUDGET  = 'over_budget';
	const KEPT_ERROR        = 'error';
	const KEPT_UNREADABLE   = 'unreadable';

	private $delivery;

	private $marks;

	private $sleep;

	private $api;

	public function __construct( ?Delivery $delivery = null, ?Marks $marks = null, $sleep = null, ?\WPML_TM_ATE_API $api = null ) {
		$this->delivery = $delivery ?: new Delivery();
		$this->marks    = $marks ?: new Marks();
		$this->sleep    = is_callable( $sleep ) ? $sleep : 'usleep';
		$this->api      = $api;
	}

	public static function completedStatuses() {
		return array_merge( Runner::READY_STATUSES, [ \WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_DELIVERED ] );
	}

	public function completedAtAte( array $ateJobIds, $deadline ) {
		$ateJobIds = array_filter(
			array_map( 'intval', $ateJobIds ),
			function ( $ateJobId ) {
				return $ateJobId > 0;
			}
		);

		if ( ! $ateJobIds ) {
			return [];
		}

		$remaining = (float) $deadline - Clock::exact();

		if ( $remaining <= 0 ) {
			return $this->keepAll( $ateJobIds, self::KEPT_BUDGET );
		}

		try {
			$answer = $this->api()->get_jobs(
				array_values( array_unique( $ateJobIds ) ),
				null,
				[ 'timeout' => max( self::CHECK_MIN_TIMEOUT, $remaining ) ]
			);
		} catch ( \Throwable $e ) {
			return $this->keepAll( $ateJobIds, self::KEPT_ERROR, $e->getMessage() );
		}

		if ( Clock::exact() > (float) $deadline ) {
			return $this->keepAll( $ateJobIds, self::KEPT_OVER_BUDGET );
		}

		if ( is_wp_error( $answer ) ) {
			return $this->keepAll( $ateJobIds, self::KEPT_ERROR, (string) $answer->get_error_code() . ': ' . $answer->get_error_message() );
		}

		$statuses = self::statusesFrom( $answer );

		if ( null === $statuses ) {
			return $this->keepAll( $ateJobIds, self::KEPT_UNREADABLE );
		}

		$completed = [];

		foreach ( $ateJobIds as $jobId => $ateJobId ) {
			$status = array_key_exists( $ateJobId, $statuses ) ? $statuses[ $ateJobId ] : null;

			if ( null !== $status && in_array( $status, self::completedStatuses(), true ) ) {
				$completed[] = $ateJobId;
				Log::write( self::LOG_KIND_CHECK, [ 'deferred' => true, 'ate_status' => $status ], (int) $jobId, $ateJobId );
				continue;
			}

			Log::write(
				self::LOG_KIND_CHECK,
				[
					'deferred'   => false,
					'ate_status' => $status,
					'reason'     => null === $status ? self::KEPT_NOT_LISTED : self::KEPT_NOT_COMPLETE,
				],
				(int) $jobId,
				$ateJobId
			);
		}

		return array_values( array_unique( $completed ) );
	}

	private function keepAll( array $ateJobIds, $reason, $message = '' ) {
		$data = [ 'deferred' => false, 'ate_status' => null, 'reason' => (string) $reason ];

		if ( '' !== (string) $message ) {
			$data['message'] = substr( (string) $message, 0, 500 );
		}

		foreach ( $ateJobIds as $jobId => $ateJobId ) {
			Log::write( self::LOG_KIND_CHECK, $data, (int) $jobId, (int) $ateJobId );
		}

		return [];
	}

	private static function statusesFrom( $answer ) {
		$items = is_object( $answer ) ? get_object_vars( $answer ) : $answer;

		if ( ! is_array( $items ) ) {
			return null;
		}

		if ( isset( $items['jobs'] ) && ( is_array( $items['jobs'] ) || is_object( $items['jobs'] ) ) ) {
			$items = is_object( $items['jobs'] ) ? get_object_vars( $items['jobs'] ) : $items['jobs'];
		}

		$statuses = [];

		foreach ( $items as $key => $item ) {
			$item = is_object( $item ) ? get_object_vars( $item ) : $item;

			if ( ! is_array( $item ) ) {
				continue;
			}

			$status = $item['status_id'] ?? ( $item['status'] ?? null );

			if ( ! is_numeric( $status ) ) {
				continue;
			}

			$id = isset( $item['ate_job_id'] ) && is_numeric( $item['ate_job_id'] )
				? (int) $item['ate_job_id']
				: ( is_numeric( $key ) ? (int) $key : 0 );

			if ( $id > 0 ) {
				$statuses[ $id ] = (int) $status;
			}
		}

		return $statuses ? $statuses : null;
	}

	private function api() {
		if ( ! $this->api ) {
			$this->api = \WPML\Container\make( \WPML_TM_ATE_API::class );
		}

		return $this->api;
	}

	public static function isCandidate( $job ) {
		if ( ! is_object( $job ) && ! is_array( $job ) ) {
			return false;
		}

		$job = (array) $job;

		return 1 === (int) ( $job['automatic'] ?? 0 )
			&& \WPML_TM_Editors::ATE === (string) ( $job['editor'] ?? '' )
			&& (int) ( $job['editor_job_id'] ?? 0 ) > 0
			&& 0 === (int) ( $job['translated'] ?? 0 )
			&& in_array( (int) ( $job['status'] ?? 0 ), [ ICL_TM_WAITING_FOR_TRANSLATOR, ICL_TM_IN_PROGRESS ], true );
	}

	public function apply( $jobId, $ateJobId, $deadline ) {
		$jobId    = (int) $jobId;
		$ateJobId = (int) $ateJobId;

		if ( Clock::exact() >= (float) $deadline ) {
			return $this->fallback( $jobId, $ateJobId, 'budget' );
		}

		Declaration::begin();

		try {
			return $this->write( $jobId, $ateJobId, (float) $deadline );
		} finally {
			Declaration::end();
		}
	}

	private function write( $jobId, $ateJobId, $deadline ) {
		$waitUntil = min( $deadline, Clock::exact() + self::LOCK_WAIT_SECONDS );
		$holder    = self::lockHolder();

		while ( true ) {
			try {
				$outcome = $this->delivery->deliver( $jobId, $holder );
			} catch ( \Throwable $e ) {
				if ( $this->wroteWithoutConfirm( $jobId ) ) {
					return $this->appliedUnconfirmed( $jobId, $ateJobId, 'error' );
				}

				return $this->fallback( $jobId, $ateJobId, 'error', $e->getMessage() );
			}

			if ( SingleJobDelivery::LOCK_BUSY !== $outcome || Clock::exact() >= $waitUntil ) {
				break;
			}

			call_user_func( $this->sleep, self::LOCK_POLL_MICROSECONDS );
		}

		if ( SingleJobDelivery::APPLIED === $outcome ) {
			if ( $this->delivery->isGivenUp( $jobId ) && $this->wasDeclinedByWrite( $jobId ) ) {
				return $this->fallback( $jobId, $ateJobId, 'declined_by_write' );
			}

			Log::write( 'written', [ 'by' => self::LOG_KIND ], $jobId, $ateJobId );
			Log::write( 'confirmed', [ 'by' => self::LOG_KIND ], $jobId, $ateJobId );
			Log::write( self::LOG_KIND, [ 'outcome' => self::APPLIED ], $jobId, $ateJobId );
			$this->delivery->announceWritten( [ $jobId => $ateJobId ] );

			return self::APPLIED;
		}

		if ( SingleJobDelivery::ALREADY_APPLIED === $outcome ) {
			Log::write(
				self::LOG_KIND,
				[
					'outcome'         => self::APPLIED,
					'already_applied' => true,
					'skipped'         => self::SKIPPED_WRITTEN_BY_RUN,
				],
				$jobId,
				$ateJobId
			);

			return self::APPLIED;
		}

		if ( SingleJobDelivery::NOT_READY === $outcome ) {
			Log::write( self::LOG_KIND, [ 'outcome' => self::NOT_COMPLETE ], $jobId, $ateJobId );

			return self::NOT_COMPLETE;
		}

		if ( SingleJobDelivery::APPLY_FAILED === $outcome && $this->wroteWithoutConfirm( $jobId ) ) {
			return $this->appliedUnconfirmed( $jobId, $ateJobId, $outcome );
		}

		return $this->fallback( $jobId, $ateJobId, (string) $outcome );
	}

	private static function lockHolder() {
		return self::LOCK_HOLDER_PREFIX . bin2hex( random_bytes( 8 ) );
	}

	private function appliedUnconfirmed( $jobId, $ateJobId, $outcome ) {
		$this->marks->set( $jobId, $ateJobId, Marks::UNCONFIRMED );

		Log::write( 'written', [ 'by' => self::LOG_KIND, 'confirmed' => false ], $jobId, $ateJobId );
		Log::write( 'confirm_failed', [ 'outcome' => (string) $outcome, 'mark' => Marks::UNCONFIRMED ], $jobId, $ateJobId );
		Log::write( self::LOG_KIND, [ 'outcome' => self::APPLIED, 'confirmed' => false ], $jobId, $ateJobId );
		$this->delivery->announceWritten( [ $jobId => $ateJobId ] );

		return self::APPLIED;
	}

	private function fallback( $jobId, $ateJobId, $reason, $message = '' ) {
		$data = [ 'outcome' => self::FALLBACK, 'reason' => (string) $reason ];

		if ( '' !== (string) $message ) {
			$data['message'] = substr( (string) $message, 0, 500 );
		}

		Log::write( self::LOG_KIND, $data, $jobId, $ateJobId );

		return self::FALLBACK;
	}

	private function wroteWithoutConfirm( $jobId ) {
		return SingleJobDelivery::ALREADY_APPLIED === $this->delivery->refusal( $jobId );
	}

	private function wasDeclinedByWrite( $jobId ) {
		$marks = $this->marks->marksOf( [ $jobId ] );

		return Marks::DECLINED === ( isset( $marks[ $jobId ]['mark'] ) ? $marks[ $jobId ]['mark'] : null );
	}
}
