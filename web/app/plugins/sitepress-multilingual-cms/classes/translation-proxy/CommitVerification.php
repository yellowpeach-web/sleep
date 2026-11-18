<?php

namespace WPML\TM\TranslationProxy;

class CommitVerification {

	public static function didPinnedBatchDispatch( $batch_name = null, $batch_id = null ) {
		$batch_name = $batch_name ?: TpBatchState::getBatchName();

		if ( ! $batch_name ) {
			return null;
		}

		$tp_ids = self::get_tp_ids_of_batch( $batch_name, null !== $batch_id );

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

		if ( null !== $batch_id ) {
			$statuses = array_values(
				array_filter(
					$statuses,
					function ( $status ) use ( $batch_id ) {
						return (int) $status->get_batch_id() === (int) $batch_id;
					}
				)
			);
			if ( ! $statuses ) {
				return null;
			}
		}

		foreach ( $statuses as $status ) {
			if ( \WPML_TP_Job_States::RECEIVED !== $status->get_status() ) {
				return true;
			}
		}

		return false;
	}

	private static function get_tp_ids_of_batch( $batch_name, $include_standalone_terms = false ) {
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
		if ( $include_standalone_terms ) {
			$term_tp_ids = $wpdb->get_col(
				"SELECT ts.tp_id
				 FROM {$wpdb->prefix}icl_translation_status ts
				 JOIN {$wpdb->prefix}icl_translations t ON t.translation_id = ts.translation_id
				 WHERE ts.batch_id = 0 AND ts.tp_id > 0 AND ts.status = 2 AND t.element_type LIKE 'tax\_%'"
			);
			$tp_ids      = array_merge( (array) $tp_ids, (array) $term_tp_ids );
		}

		return array_values( array_unique( array_filter( array_map( 'intval', (array) $tp_ids ) ) ) );
	}
}
