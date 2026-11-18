<?php

namespace WPML\EditHistory;

class ReSend {

	const HOOK = 'wpml_edit_history_resend';

	const DROPPED_ACTION = 'wpml_edit_history_delivery_dropped';

	const DELAY = 60;

	const OPTION_PREFIX = 'wpml_edit_history_resend_';

	private $rows;

	private $send;

	private $log;

	private $now;

	public function __construct( JobRows $rows, callable $send, callable $log, $now = null ) {
		$this->rows = $rows;
		$this->send = $send;
		$this->log  = $log;
		$this->now  = $now ?? 'time';
	}

	public function afterDroppedDelivery( int $jobId, int $deliveryId ) {
		$job = $this->rows->ofJob( $jobId );
		if ( ! $job ) {
			( $this->log )( 'edit_history_resend_unknown_job', [ 'job_id' => $jobId ] );

			return;
		}

		$dropped = $job['ate_job_id'] > 0 && $deliveryId > 0
			? [
				'job_id'      => $job['ate_job_id'],
				'delivery_id' => $deliveryId,
			]
			: null;

		$this->queue( $job['rid'], $dropped, (int) get_current_user_id() ?: $job['translator_id'], 'dropped' );
	}

	public function afterTakeover( int $rid ) {
		$this->queue( $rid, null, (int) get_current_user_id(), 'takeover' );
	}

	private function queue( int $rid, $dropped, int $userId, string $reason ) {
		if ( $rid <= 0 ) {
			return;
		}

		$now     = ( $this->now )();
		$marker  = $this->marker( $rid );
		$pending = $marker && wp_next_scheduled( self::HOOK, [ $rid ] );
		$newest  = $this->rows->newestJob( $rid );

		update_option(
			self::key( $rid ),
			[
				'dropped' => $dropped ?? ( $pending ? $marker['dropped'] : null ),
				'after'   => $newest ? $newest['job_id'] : 0,
				'user'    => $userId,
				'at'      => $now,
			],
			false
		);
		$this->schedule( $rid, $now + self::DELAY );

		( $this->log )(
			'edit_history_resend_queued',
			[
				'rid'     => $rid,
				'reason'  => $reason,
				'dropped' => $dropped,
				'due'     => $now + self::DELAY,
			]
		);
	}

	public function run( $rid ) {
		$rid    = (int) $rid;
		$marker = $this->marker( $rid );
		if ( ! $marker ) {
			return;
		}

		$element = $this->rows->element( $rid );
		if ( ! $element ) {
			delete_option( self::key( $rid ) );
			( $this->log )( 'edit_history_resend_skipped', [ 'rid' => $rid, 'reason' => 'the translation is gone' ] );

			return;
		}

		$savedAt = $element['translation'] > 0 ? self::savedAt( $element['translation'] ) : null;
		if ( null !== $savedAt && $savedAt + self::DELAY > ( $this->now )() ) {
			$this->schedule( $rid, $savedAt + self::DELAY );

			return;
		}

		$newest = $this->rows->newestJob( $rid );
		if ( $newest && $newest['job_id'] > $marker['after'] && \WPML_TM_Editors::ATE === $newest['editor'] && ! $newest['translated'] ) {
			delete_option( self::key( $rid ) );
			( $this->log )( 'edit_history_resend_skipped', [ 'rid' => $rid, 'reason' => 'job ' . $newest['job_id'] . ' went to ATE after the trigger' ] );

			return;
		}

		$previous = (int) get_current_user_id();
		if ( $marker['user'] > 0 && get_userdata( $marker['user'] ) ) {
			wp_set_current_user( $marker['user'] );
		}
		try {
			( $this->send )( $element );
			( $this->log )( 'edit_history_resend_sent', [ 'rid' => $rid, 'dropped' => $marker['dropped'] ] );
		} catch ( \Throwable $error ) {
			( $this->log )( 'edit_history_resend_failed', [ 'rid' => $rid, 'reason' => $error->getMessage() ] );
		} finally {
			delete_option( self::key( $rid ) );
			wp_set_current_user( $previous );
		}
	}

	public function takeDropped( int $rid ) {
		$marker = $this->marker( $rid );
		if ( ! $marker || ! $marker['dropped'] ) {
			return null;
		}

		update_option( self::key( $rid ), [ 'dropped' => null ] + $marker, false );

		return $marker['dropped'];
	}

	private function marker( int $rid ) {
		$marker = get_option( self::key( $rid ) );
		if ( ! is_array( $marker ) ) {
			return null;
		}

		$dropped = $marker['dropped'] ?? null;

		return [
			'dropped' => is_array( $dropped ) && isset( $dropped['job_id'], $dropped['delivery_id'] )
				? [
					'job_id'      => (int) $dropped['job_id'],
					'delivery_id' => (int) $dropped['delivery_id'],
				]
				: null,
			'after'   => (int) ( $marker['after'] ?? 0 ),
			'user'    => (int) ( $marker['user'] ?? 0 ),
			'at'      => (int) ( $marker['at'] ?? 0 ),
		];
	}

	private function schedule( int $rid, int $at ) {
		wp_clear_scheduled_hook( self::HOOK, [ $rid ] );
		wp_schedule_single_event( $at, self::HOOK, [ $rid ] );
	}

	private static function savedAt( int $postId ) {
		$modified = get_post_field( 'post_modified_gmt', $postId );
		$time     = is_string( $modified ) ? strtotime( $modified . ' UTC' ) : false;

		return $time > 0 ? $time : null;
	}

	private static function key( int $rid ): string {
		return self::OPTION_PREFIX . $rid;
	}
}
