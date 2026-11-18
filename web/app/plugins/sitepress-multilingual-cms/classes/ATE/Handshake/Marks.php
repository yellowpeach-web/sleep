<?php

namespace WPML\TM\ATE\Handshake;

class Marks {

	const FAILED_ON_SITE = 'failed_on_site';
	const NEEDS_RESEND   = 'needs_resend';
	const NOT_NEEDED     = 'not_needed';
	const DECLINED       = 'declined';
	const UNCONFIRMED    = 'unconfirmed';

	public function set( $jobId, $ateJobId, $mark ) {
		global $wpdb;

		$table = $wpdb->prefix . Schema::MARKS_TABLE;
		$row   = [
			'job_id'     => (int) $jobId,
			'ate_job_id' => (int) $ateJobId,
			'mark'       => (string) $mark,
			'marked_at'  => gmdate( 'Y-m-d H:i:s', time() ),
		];

		if ( ! Schema::ensureTables( $wpdb ) ) {
			return false;
		}

		return false !== $wpdb->replace( $table, $row );
	}

	public function clear( $jobId ) {
		global $wpdb;

		if ( ! Schema::ensureTables( $wpdb ) ) {
			return;
		}

		$wpdb->delete( $wpdb->prefix . Schema::MARKS_TABLE, [ 'job_id' => (int) $jobId ] );
	}

	public function marksOf( array $jobIds ) {
		global $wpdb;

		$jobIds = array_values( array_unique( array_filter( array_map( 'intval', $jobIds ) ) ) );

		if ( ! $jobIds || ! Schema::ensureTables( $wpdb ) ) {
			return [];
		}

		$suppressed = $wpdb->suppress_errors( true );

		try {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT job_id, ate_job_id, mark, marked_at FROM {$wpdb->prefix}icl_ate_handshake_marks WHERE job_id IN (" . implode( ',', array_fill( 0, count( $jobIds ), '%d' ) ) . ')',
					$jobIds
				)
			);
		} finally {
			$wpdb->suppress_errors( $suppressed );
		}

		$marks = [];

		foreach ( is_array( $rows ) ? $rows : [] as $row ) {
			$markedAt = strtotime( (string) $row->marked_at . ' UTC' );

			$marks[ (int) $row->job_id ] = [
				'ate_job_id' => (int) $row->ate_job_id,
				'mark'       => (string) $row->mark,
				'marked_at'  => false === $markedAt ? 0 : (int) $markedAt,
			];
		}

		return $marks;
	}

	public function jobsMarked( $mark ) {
		global $wpdb;

		if ( ! Schema::ensureTables( $wpdb ) ) {
			return [];
		}

		$suppressed = $wpdb->suppress_errors( true );

		try {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT job_id, ate_job_id FROM {$wpdb->prefix}icl_ate_handshake_marks WHERE mark = %s ORDER BY job_id",
					(string) $mark
				)
			);
		} finally {
			$wpdb->suppress_errors( $suppressed );
		}

		return array_map(
			function ( $row ) {
				return [
					'job_id'     => (int) $row->job_id,
					'ate_job_id' => (int) $row->ate_job_id,
				];
			},
			is_array( $rows ) ? $rows : []
		);
	}

	public function resendReasons() {
		global $wpdb;

		if ( ! Schema::ensureTables( $wpdb ) ) {
			return [];
		}

		$suppressed = $wpdb->suppress_errors( true );

		try {
			$reasons = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT COALESCE(e.error_message, '') FROM {$wpdb->prefix}icl_ate_handshake_marks m LEFT JOIN {$wpdb->prefix}icl_translate_unsolvable_jobs e ON e.job_id = m.job_id WHERE m.mark = %s",
					self::NEEDS_RESEND
				)
			);
		} finally {
			$wpdb->suppress_errors( $suppressed );
		}

		return is_array( $reasons ) ? array_map( 'strval', $reasons ) : [];
	}

	public function withUnconfirmed( array $summary ) {
		$unconfirmed = count( $this->jobsMarked( self::UNCONFIRMED ) );

		if ( $unconfirmed > 0 ) {
			$summary['count'] = (int) ( isset( $summary['count'] ) ? $summary['count'] : 0 ) + $unconfirmed;
		}

		return $summary;
	}
}
