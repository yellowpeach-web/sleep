<?php

namespace WPML\StringTranslation\Infrastructure\StringCore\Query;

final class FailedStatement {

	public static function throwIfFailed( $wpdb, string $what ) {
		$error = isset( $wpdb->last_error ) && is_string( $wpdb->last_error ) ? $wpdb->last_error : '';

		if ( '' !== $error ) {
			throw new \RuntimeException( esc_html( sprintf( 'String Translation could not %s: %s', $what, $error ) ) );
		}
	}
}
