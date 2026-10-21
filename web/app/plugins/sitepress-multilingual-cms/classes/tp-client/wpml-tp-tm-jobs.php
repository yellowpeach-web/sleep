<?php

class WPML_TP_TM_Jobs {

	const CACHE_BATCH_ID = 'wpml_tp_tm_jobs_batch_id';

	private $wpdb;

	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function get_batch_id( $job_id ) {
		$cached_batch_id = wp_cache_get( $job_id, self::CACHE_BATCH_ID );

		if ( $cached_batch_id ) {
			return $cached_batch_id;
		}

		$wpdb  = $this->wpdb;
		$batch_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT tb.tp_id FROM {$wpdb->prefix}icl_translation_batches AS tb
				LEFT JOIN {$wpdb->prefix}icl_translation_status AS ts ON tb.id = ts.batch_id
				LEFT JOIN {$wpdb->prefix}icl_translate_job AS tj ON ts.rid = tj.rid
				WHERE tj.job_id = %d",
				$job_id
			)
		);

		wp_cache_set( $job_id, $batch_id, self::CACHE_BATCH_ID );

		return $batch_id;
	}
}
