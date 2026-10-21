<?php

namespace WPML\Comments\Counts;

class Scheduler {

	const EVENT = 'wpml_comment_counts_recount';

	const INTERVAL_FILTER = 'wpml_comment_counts_recount_interval';

	const DEFAULT_INTERVAL = 300;

	const MIN_INTERVAL = 60;

	const LATE_LIMIT = 3600;

	const LATE_RECOUNT_PRIORITY = 1000;

	const LATE_RECOUNT_CAPABILITY = 'edit_posts';

	private static $lateRecountAdded = false;

	private $lateRecount;

	private $lateEventAt = 0;

	public static function resetRequestCache() {
		self::$lateRecountAdded = false;
	}

	public function ensureScheduled( ?array $store = null ) {
		if ( $this->isScheduled() ) {
			return;
		}

		$now = time();
		$at  = null === $store ? $now : max( $now, (int) $store['built_at'] + $this->interval() );

		wp_schedule_single_event( $at, self::EVENT );
	}

	public function scheduleAfterInterval() {
		if ( $this->isScheduled() ) {
			return;
		}

		wp_schedule_single_event( time() + $this->interval(), self::EVENT );
	}

	public function scheduleSafetyRecount( $delay ) {
		$at   = time() + (int) $delay;
		$args = array( uniqid( 'batch-', true ) );

		return true === wp_schedule_single_event( $at, self::EVENT, $args ) ? array( $at, $args ) : null;
	}

	public function unscheduleSafetyRecount( array $event ) {
		wp_unschedule_event( $event[0], self::EVENT, $event[1] );
	}

	public function interval() {
		$seconds = apply_filters( self::INTERVAL_FILTER, self::DEFAULT_INTERVAL );

		if ( ! is_int( $seconds ) ) {
			return self::DEFAULT_INTERVAL;
		}

		return max( self::MIN_INTERVAL, $seconds );
	}

	public function clear() {
		wp_unschedule_hook( self::EVENT );
		$this->cancelLateRecount();
	}

	public function cancelLateRecount() {
		if ( $this->lateRecount ) {
			remove_action( 'shutdown', array( $this, 'runLateRecount' ), self::LATE_RECOUNT_PRIORITY );
			$this->lateRecount = null;
		}
	}

	public function addLateRecount( Recount $recount ) {
		if ( self::$lateRecountAdded || ! is_admin() ) {
			return;
		}

		$at = wp_next_scheduled( self::EVENT );
		if ( false === $at || time() - (int) $at <= self::LATE_LIMIT || ! current_user_can( self::LATE_RECOUNT_CAPABILITY ) ) {
			return;
		}

		self::$lateRecountAdded = true;
		$this->lateRecount      = $recount;
		$this->lateEventAt      = (int) $at;

		add_action( 'shutdown', array( $this, 'runLateRecount' ), self::LATE_RECOUNT_PRIORITY, 0 );
	}

	public function runLateRecount() {
		$recount = $this->lateRecount;
		if ( ! $recount ) {
			return;
		}
		$this->lateRecount = null;

		$this->finishResponse();
		wp_unschedule_event( $this->lateEventAt, self::EVENT );
		$recount->runFromCron();
	}

	protected function finishResponse() {
		if ( function_exists( 'ignore_user_abort' ) ) {
			ignore_user_abort( true );
		}

		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		} elseif ( function_exists( 'litespeed_finish_request' ) ) {
			litespeed_finish_request();
		}
	}

	private function isScheduled() {
		return false !== wp_next_scheduled( self::EVENT );
	}
}
