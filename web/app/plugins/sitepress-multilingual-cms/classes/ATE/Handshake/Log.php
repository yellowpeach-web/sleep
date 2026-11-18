<?php

namespace WPML\TM\ATE\Handshake;

class Log {

	const KINDS = [
		'wake_received',
		'wake_rejected',
		'drive_received',
		'run_start',
		'run_end',
		'gate',
		'fetch',
		'written',
		'confirmed',
		'declined',
		'closure_seen',
		'unknown_seen',
		'notice_shown',
		'retry_sent',
		'retry_refused',
		'acceptance_recorded',
		'acceptance_refused',
		'loopback_probe',
		'credit_resume',
		'confirm_failed',
		'unconfirmed_cleared',
		'pending_unnamed',
		'owed_replay',
		'save_apply',
		'save_check',
	];

	private static $testWriter = null;

	public static function write( $kind, array $data = [], $jobId = null, $ateJobId = null, $requestId = null ) {
		$row = [
			't'          => self::timestamp(),
			'kind'       => substr( (string) $kind, 0, 32 ),
			'job_id'     => null === $jobId ? null : (int) $jobId,
			'ate_job_id' => null === $ateJobId ? null : (int) $ateJobId,
			'request_id' => null === $requestId ? Declaration::requestId() : (string) $requestId,
			'data'       => (string) wp_json_encode( $data ),
		];

		if ( self::$testWriter ) {
			call_user_func( self::$testWriter, $row );

			return;
		}

		try {
			self::insert( $row );
		} catch ( \Throwable $e ) {
			return;
		}
	}

	private static function insert( array $row ) {
		global $wpdb;

		if ( ! Schema::ensureTables( $wpdb ) ) {
			return;
		}

		$suppressed = $wpdb->suppress_errors( true );

		try {
			$wpdb->insert( $wpdb->prefix . Schema::LOG_TABLE, $row );
		} finally {
			$wpdb->suppress_errors( $suppressed );
		}
	}

	private static function timestamp() {
		$exact   = Clock::exact();
		$seconds = (int) floor( $exact );

		return gmdate( 'Y-m-d H:i:s', $seconds ) . sprintf( '.%03d', (int) floor( ( $exact - $seconds ) * 1000 ) );
	}

	public static function read( $limit, array $kinds = [] ) {
		global $wpdb;

		try {
			if ( ! Schema::ensureTables( $wpdb ) ) {
				return [];
			}

			$kinds = array_map( 'strval', array_values( $kinds ? $kinds : self::KINDS ) );
			$args  = array_merge( $kinds, [ max( 1, (int) $limit ) ] );

			$suppressed = $wpdb->suppress_errors( true );

			try {
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, t, kind, job_id, ate_job_id, data FROM {$wpdb->prefix}icl_ate_handshake_log WHERE kind IN (" . implode( ',', array_fill( 0, count( $kinds ), '%s' ) ) . ') ORDER BY id DESC LIMIT %d', $args ) );
			} finally {
				$wpdb->suppress_errors( $suppressed );
			}
		} catch ( \Throwable $e ) {
			return [];
		}

		$out = [];

		foreach ( is_array( $rows ) ? $rows : [] as $row ) {
			$data = json_decode( (string) $row->data, true );
			$time = strtotime( (string) $row->t . ' UTC' );

			$out[] = [
				'id'         => (int) $row->id,
				't'          => false === $time ? 0 : (int) $time,
				'kind'       => (string) $row->kind,
				'job_id'     => null === $row->job_id ? null : (int) $row->job_id,
				'ate_job_id' => null === $row->ate_job_id ? null : (int) $row->ate_job_id,
				'data'       => is_array( $data ) ? $data : [],
			];
		}

		return $out;
	}

	public static function captureForTests( $writer ) {
		self::$testWriter = $writer;
	}
}
