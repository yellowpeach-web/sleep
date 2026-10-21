<?php

namespace WPML\PB\Gutenberg;

class NativeEditorStringTranslations {

	public function get( array $string_ids, $language ) {
		global $wpdb;

		$string_ids = array_values( array_filter( array_map( 'intval', $string_ids ) ) );

		if ( ! $string_ids ) {
			return [];
		}

		$placeholders = implode( ',', array_fill( 0, count( $string_ids ), '%d' ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT string_id, value, status FROM {$wpdb->prefix}icl_string_translations WHERE language = %s AND string_id IN ({$placeholders})",
				array_merge( [ $language ], $string_ids )
			)
		);

		$translations = [];

		foreach ( (array) $rows as $row ) {
			$translations[ (int) $row->string_id ] = [
				'value'  => null === $row->value ? null : (string) $row->value,
				'status' => (int) $row->status,
			];
		}

		return $translations;
	}
}
