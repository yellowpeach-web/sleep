<?php

namespace WPML\ST;

class SameLanguageTranslations {

	const ACTION = 'wpml_st_same_language_translations_removed';

	private $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function remove_for_strings( array $string_ids, $source ) {
		$string_ids = array_values( array_unique( array_filter( array_map( 'intval', $string_ids ) ) ) );

		if ( ! $string_ids ) {
			return [];
		}

		$wpdb = $this->wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT st.id, st.string_id, st.language, st.value, s.value AS source_value
				FROM {$wpdb->prefix}icl_string_translations st
				INNER JOIN {$wpdb->prefix}icl_strings s ON s.id = st.string_id
				WHERE st.language = s.language AND st.string_id IN (" . implode( ',', array_fill( 0, count( $string_ids ), '%d' ) ) . ')',
				$string_ids
			)
		);

		return $this->remove( is_array( $rows ) ? $rows : [], $source );
	}

	public function remove_for_package( $package_id, $source ) {
		$wpdb = $this->wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT st.id, st.string_id, st.language, st.value, s.value AS source_value
				FROM {$wpdb->prefix}icl_string_translations st
				INNER JOIN {$wpdb->prefix}icl_strings s ON s.id = st.string_id
				WHERE st.language = s.language AND s.string_package_id = %d",
				[ (int) $package_id ]
			)
		);

		return $this->remove( is_array( $rows ) ? $rows : [], $source );
	}

	public function remove_all() {
		$wpdb = $this->wpdb;

		$removed = $wpdb->query(
			"DELETE st FROM {$wpdb->prefix}icl_string_translations st
			 INNER JOIN {$wpdb->prefix}icl_strings s ON s.id = st.string_id
			 WHERE st.language = s.language
			 AND ( st.value IS NULL OR st.value = '' OR BINARY st.value = BINARY s.value )"
		);

		if ( ! is_numeric( $removed ) ) {
			return false;
		}

		$this->log( 'upgrade', (int) $removed, [] );

		return (int) $removed;
	}

	private function remove( array $rows, $source ) {
		$wpdb = $this->wpdb;

		if ( ! $rows ) {
			return [];
		}

		$row_ids    = [];
		$string_ids = [];
		$dropped    = [];
		foreach ( $rows as $row ) {
			$row_ids[]    = (int) $row->id;
			$string_ids[] = (int) $row->string_id;

			if ( null !== $row->value && '' !== (string) $row->value && (string) $row->value !== (string) $row->source_value ) {
				$dropped[] = [
					'string_id' => (int) $row->string_id,
					'language'  => (string) $row->language,
					'value'     => (string) $row->value,
				];
			}
		}

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}icl_string_translations WHERE id IN (" . implode( ',', array_fill( 0, count( $row_ids ), '%d' ) ) . ')',
				$row_ids
			)
		);

		$this->log( $source, count( $row_ids ), $dropped );

		return array_values( array_unique( $string_ids ) );
	}

	private function log( $source, $removed, array $dropped ) {
		do_action( self::ACTION, $source, $removed, $dropped );

		if ( $removed < 1 ) {
			return;
		}

		if ( $dropped || ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
			error_log(
				'WPML String Translation: same_language_translations_removed '
				. wp_json_encode(
					[
						'source'  => $source,
						'removed' => $removed,
						'dropped' => $dropped,
					]
				)
			);
		}
	}
}
