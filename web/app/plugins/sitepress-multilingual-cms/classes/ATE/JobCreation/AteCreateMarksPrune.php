<?php

namespace WPML\TM\ATE\JobCreation;

class AteCreateMarksPrune implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const EVENT    = 'wpml_tm_ate_create_marks_prune';
	const SCHEDULE = 'daily';

	const MARK_MAX_AGE = 2592000;

	const IN_FLIGHT_GRACE = 3600;

	const PAGE = 500;

	private $wpdb;

	public function __construct( $wpdb = null ) {
		$this->wpdb = $wpdb;
	}

	public function add_hooks() {
		add_action( 'init', [ $this, 'schedule' ] );
		add_action( self::EVENT, [ $this, 'run' ] );
		add_action( 'wpml_deactivated', [ $this, 'unschedule' ] );
		add_action( 'wpml_reset_plugins_before', [ $this, 'unschedule' ] );
	}

	public function schedule() {
		if ( ! wp_next_scheduled( self::EVENT ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, self::SCHEDULE, self::EVENT );
		}
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::EVENT );
	}

	public function run() {
		try {
			$this->prune( time() );
		} catch ( \Throwable $e ) {
			$this->logFailure( $e );
		}
	}

	private function logFailure( \Throwable $error ) {
		if ( ! class_exists( '\\WPML\\TM\\Jobs\\JobLog' ) ) {
			return;
		}

		try {
			$ownsRequest = \WPML\TM\Jobs\JobLog::maybeInitBackgroundRequest( 'wpml-ate-create-marks-prune' );
			try {
				\WPML\TM\Jobs\JobLog::createNewGroup( \WPML\TM\Jobs\JobLog::GROUP_ID_SEND_JOBS, 'ATE create marks prune' );
				\WPML\TM\Jobs\JobLog::addError( 'ate_create_marks_prune_failed', [ 'error' => get_class( $error ) ] );
				\WPML\TM\Jobs\JobLog::finishCurrentGroup();
			} finally {
				if ( $ownsRequest ) {
					\WPML\TM\Jobs\JobLog::flushBackgroundRequest();
				}
			}
		} catch ( \Throwable $logError ) {
			return;
		}
	}

	public function prune( $now ) {
		return [
			'inFlight' => $this->prunePrefix( AteCreateReconciler::IN_FLIGHT_OPTION_PREFIX, $now ),
			'marks'    => $this->prunePrefix( AteCreateReconciler::UNCONFIRMED_OPTION_PREFIX, $now ),
		];
	}

	private function prunePrefix( $prefix, $now ) {
		$wpdb    = $this->wpdb();
		$deleted = 0;
		$after   = 0;

		do {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT o.option_id, o.option_name, o.option_value, j.job_id, j.editor_job_id, j.completed_date
					FROM {$wpdb->options} o
					LEFT JOIN {$wpdb->prefix}icl_translate_job j ON j.job_id = CAST( SUBSTRING( o.option_name, %d ) AS UNSIGNED )
					WHERE o.option_name LIKE %s AND o.option_id > %d
					ORDER BY o.option_id
					LIMIT %d",
					strlen( $prefix ) + 1,
					$wpdb->esc_like( $prefix ) . '%',
					$after,
					self::PAGE
				)
			);
			$rows    = is_array( $rows ) ? $rows : [];
			$fetched = count( $rows );

			foreach ( $rows as $row ) {
				$after = max( $after, (int) $row->option_id );

				$goes = AteCreateReconciler::IN_FLIGHT_OPTION_PREFIX === $prefix
					? $this->inFlightMarkerGoes( $row, $now )
					: $this->markGoes( $row, $now );

				if ( $goes && $this->deleteIfUnchanged( $row ) ) {
					$deleted++;
				}
			}
		} while ( self::PAGE === $fetched );

		return $deleted;
	}

	private function inFlightMarkerGoes( $row, $now ) {
		if ( null === $row->job_id ) {
			return true;
		}

		return ! is_numeric( $row->option_value ) || (int) $row->option_value <= $now - self::IN_FLIGHT_GRACE;
	}

	private function markGoes( $row, $now ) {
		if ( null === $row->job_id ) {
			return true;
		}

		if ( (int) $row->editor_job_id <= 0 || ! $this->isDate( $row->completed_date ) ) {
			return false;
		}

		$cutoff    = $now - self::MARK_MAX_AGE;
		$completed = strtotime( $row->completed_date );
		$at        = $this->markedAt( $row->option_value );

		return false !== $completed && $completed <= $cutoff && null !== $at && $at <= $cutoff;
	}

	private function isDate( $value ) {
		return is_string( $value ) && '' !== $value && 0 !== strpos( $value, '0000-00-00' );
	}

	private function markedAt( $optionValue ) {
		$mark = maybe_unserialize( $optionValue );

		if ( is_array( $mark ) ) {
			return isset( $mark['at'] ) && is_numeric( $mark['at'] ) ? (int) $mark['at'] : null;
		}

		return is_numeric( $mark ) ? (int) $mark : null;
	}

	private function deleteIfUnchanged( $row ) {
		$deleted = $this->wpdb()->delete(
			$this->wpdb()->options,
			[
				'option_name'  => $row->option_name,
				'option_value' => $row->option_value,
			]
		);

		if ( ! $deleted ) {
			return false;
		}

		wp_cache_delete( $row->option_name, 'options' );

		return true;
	}

	private function wpdb() {
		if ( ! $this->wpdb ) {
			global $wpdb;
			$this->wpdb = $wpdb;
		}

		return $this->wpdb;
	}
}
