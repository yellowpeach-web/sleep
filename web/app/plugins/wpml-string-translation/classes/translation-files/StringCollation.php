<?php

namespace WPML\ST\TranslationFile;

trait StringCollation {

	private function getCollateForContextColumn( \wpdb $wpdb ) {
		static $collation_suffix = null;

		if ( null !== $collation_suffix ) {
			return $collation_suffix;
		}

		$suffixes_by_charset = [
			'utf8'    => 'COLLATE utf8_bin',
			'utf8mb3' => 'COLLATE utf8mb3_bin',
			'utf8mb4' => 'COLLATE utf8mb4_bin',
			'latin1'  => 'COLLATE latin1_bin',
			'latin2'  => 'COLLATE latin2_bin',
			'ascii'   => 'COLLATE ascii_bin',
			'binary'  => 'COLLATE binary',
		];

		$cached = \get_transient( 'wpml_st_context_collation' );

		if ( '' === $cached || in_array( $cached, $suffixes_by_charset, true ) ) {
			$collation_suffix = $cached;

			return $collation_suffix;
		}

		$collation_suffix = '';

		$sql = "
			SELECT COLLATION_NAME
			 FROM information_schema.columns
			 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$wpdb->prefix}icl_strings' AND COLUMN_NAME = 'context'
		";
		$collation = $wpdb->get_var( $sql );
		if ( $collation ) {
			list( $type ) = explode( '_', $collation );

			$collation_suffix = isset( $suffixes_by_charset[ $type ] ) ? $suffixes_by_charset[ $type ] : '';
		}

		\set_transient( 'wpml_st_context_collation', $collation_suffix );

		return $collation_suffix;
	}

}
