<?php

namespace WPML\TM\ATE\TranslateEverything;

class TermWriteRefusal {

	const TERM_NAME_MAX_LENGTH = 200;

	public static function nameOverLimit( $translatedName, $taxonomy, $wpdb ) {
		$stored = function_exists( 'sanitize_term_field' )
			? wp_unslash( sanitize_term_field( 'name', $translatedName, 0, $taxonomy, 'db' ) )
			: $translatedName;
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( (string) $stored, 'UTF-8' ) : strlen( (string) $stored );
		$limit  = self::nameMaxLength( $wpdb );

		return $length > $limit ? [ (int) $length, (int) $limit ] : null;
	}

	public static function tooLongReason( $template, $translatedName, $taxonomy, $sourceTermId, $targetLang, $wpdb ) {
		$over = self::nameOverLimit( $translatedName, $taxonomy, $wpdb );

		return $over ? sprintf( $template, $over[0], $over[1], (int) $sourceTermId, (string) $targetLang ) : '';
	}

	public static function nameMaxLength( $wpdb ) {
		$column = is_object( $wpdb ) && method_exists( $wpdb, 'get_col_length' ) ? $wpdb->get_col_length( $wpdb->terms, 'name' ) : false;

		if ( is_array( $column ) && isset( $column['type'], $column['length'] ) && 'char' === $column['type'] && (int) $column['length'] > 0 ) {
			return (int) $column['length'];
		}

		return self::TERM_NAME_MAX_LENGTH;
	}

	public static function fromWriteError( $error, $sourceTermId, $sourceName, $targetLang ) {
		$prefix = sprintf(
			'the %1$s translation of term %2$d (%3$s) could not be written',
			$targetLang,
			(int) $sourceTermId,
			self::plainName( $sourceName )
		);

		if ( ! $error instanceof \WP_Error ) {
			return $prefix . ': the term write returned no term';
		}

		$message = self::plainName( (string) preg_replace( '/<[^>]*>/', '', $error->get_error_message() ) );
		$reason  = sprintf( '%1$s: %2$s [%3$s]', $prefix, $message, $error->get_error_code() );

		$data   = $error->get_error_data();
		$detail = is_string( $data ) ? self::plainName( (string) preg_replace( '/<[^>]*>/', '', $data ) ) : '';

		return '' !== $detail ? $reason . ' ' . $detail : $reason;
	}

	public static function plainName( $name ) {
		return trim( html_entity_decode( (string) $name, ENT_QUOTES, 'UTF-8' ) );
	}
}
