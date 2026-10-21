<?php

namespace WPML\ST\PackageTranslation;

class UniqueKey {

	const NAME = 'uc_kind_slug_name';

	private static function exists( \wpdb $wpdb ) {
		$keys = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW KEYS FROM {$wpdb->prefix}icl_string_packages WHERE Key_name = %s AND Non_unique = 0",
				self::NAME
			)
		);

		return is_array( $keys ) && count( $keys ) > 0;
	}

	public static function add( \wpdb $wpdb ) {
		if ( self::exists( $wpdb ) ) {
			return true;
		}

		$wereSuppressed = $wpdb->suppress_errors( true );
		$isAdded = false !== $wpdb->query( "ALTER TABLE {$wpdb->prefix}icl_string_packages ADD UNIQUE KEY `uc_kind_slug_name` (`kind_slug`, `name`)" );
		$wpdb->suppress_errors( $wereSuppressed );

		if ( ! $isAdded ) {
			self::logUnexpectedRefusal( $wpdb );
		}

		return $isAdded;
	}

	private static function logUnexpectedRefusal( \wpdb $wpdb ) {
		if ( 0 === strpos( $wpdb->last_error, 'Duplicate entry' ) ) {
			return;
		}

		error_log( 'WPML String Translation: the unique key ' . self::NAME . ' was not added to ' . $wpdb->prefix . 'icl_string_packages: ' . $wpdb->last_error );
	}
}
