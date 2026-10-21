<?php

class WPML_Translation_Records_Delete {

	public static function translations_by_ids( array $translation_ids ) {
		$translation_ids = self::to_id_list( $translation_ids );
		if ( ! $translation_ids ) {
			return 0;
		}

		self::status_by_translation_ids( $translation_ids );

		return self::delete_by_translation_ids( 'icl_translations', $translation_ids );
	}

	public static function status_by_translation_ids( array $translation_ids ) {
		$translation_ids = self::to_id_list( $translation_ids );
		if ( ! $translation_ids ) {
			return 0;
		}

		self::jobs_by_translation_ids( $translation_ids );

		return self::delete_by_translation_ids( 'icl_translation_status', $translation_ids );
	}

	private static function delete_by_translation_ids( $table, array $translation_ids ) {
		global $wpdb;

		$in = wpml_prepare_in( $translation_ids, '%d' );

		return $wpdb->query( "DELETE FROM {$wpdb->prefix}{$table} WHERE translation_id IN ({$in})" );
	}

	public static function translations_where( $where, array $args = array(), $limit = 0 ) {
		global $wpdb;

		$sql = "SELECT translation_id FROM {$wpdb->prefix}icl_translations WHERE " . $where;
		if ( $limit > 0 ) {
			$sql .= ' LIMIT ' . (int) $limit;
		}

		if ( $args ) {
			$sql = $wpdb->prepare( $sql, $args );
		}

		return self::translations_by_ids( (array) $wpdb->get_col( $sql ) );
	}

	public static function translations_by_columns( array $where, array $formats = array() ) {
		$conditions = array();
		$args       = array();
		$index      = 0;

		foreach ( $where as $column => $value ) {
			$column = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $column );

			if ( null === $value ) {
				$conditions[] = "`{$column}` IS NULL";
			} else {
				$format       = isset( $formats[ $index ] ) ? $formats[ $index ] : '%s';
				$conditions[] = "`{$column}` = {$format}";
				$args[]       = $value;
			}

			++$index;
		}

		if ( ! $conditions ) {
			return 0;
		}

		return self::translations_where( implode( ' AND ', $conditions ), $args );
	}

	public static function jobs_by_ids( array $job_ids ) {
		global $wpdb;

		$job_ids = self::to_id_list( $job_ids );
		if ( ! $job_ids ) {
			return 0;
		}

		$in = wpml_prepare_in( $job_ids, '%d' );


		$wpdb->query( "DELETE FROM {$wpdb->prefix}icl_translate WHERE job_id IN ({$in})" );

		return $wpdb->query( "DELETE FROM {$wpdb->prefix}icl_translate_job WHERE job_id IN ({$in})" );
	}

	public static function jobs_by_rids( array $rids ) {
		global $wpdb;

		$rids = self::to_id_list( $rids );
		if ( ! $rids ) {
			return 0;
		}

		$in = wpml_prepare_in( $rids, '%d' );

		$job_ids = $wpdb->get_col( "SELECT job_id FROM {$wpdb->prefix}icl_translate_job WHERE rid IN ({$in})" );

		return self::jobs_by_ids( (array) $job_ids );
	}

	public static function jobs_by_translation_ids( array $translation_ids ) {
		global $wpdb;

		$translation_ids = self::to_id_list( $translation_ids );
		if ( ! $translation_ids ) {
			return 0;
		}

		$in = wpml_prepare_in( $translation_ids, '%d' );

		$rids = $wpdb->get_col( "SELECT rid FROM {$wpdb->prefix}icl_translation_status WHERE translation_id IN ({$in})" );

		return self::jobs_by_rids( (array) $rids );
	}

	private static function to_id_list( array $values ) {
		return array_values( array_unique( array_filter( array_map( 'intval', $values ) ) ) );
	}
}
