<?php

namespace WPML\TM\TranslationProxy;

class CommitVerification {

	public static function didPinnedBatchDispatch() {
		$batch_name = TpBatchState::getBatchName();

		if ( ! $batch_name ) {
			return null;
		}

		$tp_ids = self::get_tp_ids_of_batch( $batch_name );

		if ( ! $tp_ids ) {
			return null;
		}

		try {
			$statuses = wpml_tm_get_tp_jobs_api()->get_jobs_statuses( $tp_ids );
		} catch ( \Throwable $e ) {
			return null;
		}

		if ( ! is_array( $statuses ) || array() === $statuses ) {
			return null;
		}

		foreach ( $statuses as $status ) {
			if ( \WPML_TP_Job_States::RECEIVED !== $status->get_status() ) {
				return true;
			}
		}

		return false;
	}

	private static function get_tp_ids_of_batch( $batch_name ) {
		global $wpdb;

		if ( ! $wpdb ) {
			return array();
		}

		$tp_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ts.tp_id
				 FROM {$wpdb->prefix}icl_translation_status ts
				 JOIN {$wpdb->prefix}icl_translation_batches b ON b.id = ts.batch_id
				 WHERE b.batch_name = %s AND ts.tp_id > 0",
				$batch_name
			)
		);

		return array_values( array_filter( array_map( 'intval', (array) $tp_ids ) ) );
	}
}
