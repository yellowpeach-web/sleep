<?php

namespace WPML\TM\ATE\PullDelivery;

class PendingJobs {

	public function summary() {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT( * ) AS pending_count,
				        MIN( waiting.sent_at ) AS oldest_at,
				        COALESCE( SUM( waiting.newest_state = 2 ), 0 ) AS automatic_count,
				        MIN( CASE WHEN waiting.newest_state = 2 THEN waiting.sent_at END ) AS oldest_automatic_at
				   FROM (
				        SELECT UNIX_TIMESTAMP( MIN( status.timestamp ) ) AS sent_at,
				               (
				                   SELECT CASE
				                              WHEN newest.editor = %s AND newest.editor_job_id > 0
				                              THEN 1 + ( newest.automatic = %d )
				                              ELSE 0
				                          END
				                     FROM {$wpdb->prefix}icl_translate_job newest
				                    WHERE newest.job_id = (
				                              SELECT MAX( latest.job_id )
				                                FROM {$wpdb->prefix}icl_translate_job latest
				                               WHERE latest.rid = status.rid
				                          )
				               ) AS newest_state
				          FROM {$wpdb->prefix}icl_translation_status status
				         WHERE status.status IN ( %d, %d )
				         GROUP BY status.rid
				   ) waiting
				  WHERE waiting.newest_state > 0",
				\WPML_TM_Editors::ATE,
				1,
				ICL_TM_IN_PROGRESS,
				ICL_TM_WAITING_FOR_TRANSLATOR
			)
		);

		if ( ! is_object( $row ) ) {
			return self::nothing();
		}

		return [
			'count'               => (int) $row->pending_count,
			'oldest_at'           => (int) $row->oldest_at,
			'automatic_count'     => isset( $row->automatic_count ) ? (int) $row->automatic_count : 0,
			'oldest_automatic_at' => isset( $row->oldest_automatic_at ) ? (int) $row->oldest_automatic_at : 0,
		];
	}

	public function stillPending( array $ateJobIds ) {
		global $wpdb;

		$ateJobIds = array_values( array_unique( array_filter( array_map( 'intval', $ateJobIds ) ) ) );

		if ( ! $ateJobIds ) {
			return [];
		}

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT job.editor_job_id
				   FROM {$wpdb->prefix}icl_translate_job job
				   JOIN {$wpdb->prefix}icl_translation_status status ON status.rid = job.rid
				  WHERE job.editor_job_id IN (" . implode( ',', array_fill( 0, count( $ateJobIds ), '%d' ) ) . ")
				    AND job.editor = %s
				    AND status.status IN ( %d, %d )
				    AND job.job_id = (
				            SELECT MAX( latest.job_id )
				              FROM {$wpdb->prefix}icl_translate_job latest
				             WHERE latest.rid = job.rid
				        )",
				array_merge( $ateJobIds, [ \WPML_TM_Editors::ATE, ICL_TM_IN_PROGRESS, ICL_TM_WAITING_FOR_TRANSLATOR ] )
			)
		);

		return array_map( 'intval', is_array( $ids ) ? $ids : [] );
	}

	public static function nothing() {
		return [ 'count' => 0, 'oldest_at' => 0, 'automatic_count' => 0, 'oldest_automatic_at' => 0 ];
	}
}
