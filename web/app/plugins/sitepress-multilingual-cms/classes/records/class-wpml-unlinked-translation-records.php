<?php

class WPML_Unlinked_Translation_Records {

	const CHUNK_SIZE = 100;

	public static function sweep_all( $wpdb ) {
		$removed = 0;

		do {
			$fetched  = 0;
			$deleted  = self::unlinked_status_pass( $wpdb, self::CHUNK_SIZE, $fetched );
			$removed += $deleted;
		} while ( $deleted > 0 && $fetched >= self::CHUNK_SIZE );

		do {
			$fetched  = 0;
			$deleted  = self::parentless_content_pass( $wpdb, self::CHUNK_SIZE, $fetched );
			$removed += $deleted;
		} while ( $deleted > 0 && $fetched >= self::CHUNK_SIZE );

		return $removed;
	}

	public static function sweep_batch( $wpdb, $limit ) {
		$limit   = max( 1, (int) $limit );
		$fetched = 0;

		return self::unlinked_status_pass( $wpdb, $limit, $fetched )
			+ self::parentless_content_pass( $wpdb, $limit, $fetched );
	}

	private static function unlinked_status_pass( $wpdb, $limit, &$fetched ) {
		$rids    = $wpdb->get_col(
			"SELECT rid FROM {$wpdb->prefix}icl_translation_status
			WHERE translation_id NOT IN (SELECT translation_id FROM {$wpdb->prefix}icl_translations)
			LIMIT " . (int) $limit
		);
		$fetched = count( (array) $rids );

		if ( ! $rids ) {
			return 0;
		}

		$jids = $wpdb->get_col(
			"SELECT job_id FROM {$wpdb->prefix}icl_translate_job WHERE rid IN (" . wpml_prepare_in( $rids, '%d' ) . ')'
		);

		if ( $jids ) {
			foreach ( array_chunk( $jids, self::CHUNK_SIZE ) as $batch ) {
				$in = wpml_prepare_in( $batch, '%d' );
				$wpdb->query( "DELETE FROM {$wpdb->prefix}icl_translate WHERE job_id IN ({$in})" );
				$wpdb->query( "DELETE FROM {$wpdb->prefix}icl_translate_job WHERE job_id IN ({$in})" );
			}
		}

		$deleted = 0;
		foreach ( array_chunk( $rids, self::CHUNK_SIZE ) as $batch ) {
			$deleted += (int) $wpdb->query(
				"DELETE FROM {$wpdb->prefix}icl_translation_status WHERE rid IN (" . wpml_prepare_in( $batch, '%d' ) . ')'
			);
		}

		return $deleted;
	}

	private static function parentless_content_pass( $wpdb, $limit, &$fetched ) {
		$orphans = $wpdb->get_col(
			"SELECT t.tid FROM {$wpdb->prefix}icl_translate t
			LEFT JOIN {$wpdb->prefix}icl_translate_job j ON j.job_id = t.job_id
			WHERE j.job_id IS NULL
			LIMIT " . (int) $limit
		);
		$fetched = count( (array) $orphans );

		if ( ! $orphans ) {
			return 0;
		}

		$deleted = 0;
		foreach ( array_chunk( $orphans, self::CHUNK_SIZE ) as $batch ) {
			$deleted += (int) $wpdb->query(
				"DELETE FROM {$wpdb->prefix}icl_translate WHERE tid IN (" . wpml_prepare_in( $batch, '%d' ) . ')'
			);
		}

		return $deleted;
	}
}
