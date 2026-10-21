<?php

namespace WPML\Notices;

class NoticeStoreRepair {

	public static function readSafely( $option_name, $wpdb = null ) {
		try {
			$stored = get_option( $option_name );

			return is_array( $stored ) ? $stored : [];
		} catch ( \Throwable $throwable ) {
			return self::repair( $option_name, $throwable, $wpdb );
		}
	}

	public static function clean( array $stored, &$dropped = null ) {
		$dropped = 0;
		$clean   = [];

		foreach ( $stored as $group => $entries ) {
			if ( ! is_array( $entries ) ) {
				continue;
			}

			$kept = [];
			foreach ( $entries as $id => $notice ) {
				if ( ! $notice instanceof \WPML_Notice ) {
					continue;
				}

				$dropped    += self::stripUnstorableCallbacks( $notice );
				$kept[ $id ] = $notice;
			}

			if ( $kept ) {
				$clean[ $group ] = $kept;
			}
		}

		return $clean;
	}

	public static function isStorableCallback( $callback ) {
		if ( is_string( $callback ) ) {
			return true;
		}

		return is_array( $callback )
			&& [ 0, 1 ] === array_keys( $callback )
			&& is_string( $callback[0] )
			&& is_string( $callback[1] );
	}

	private static function repair( $option_name, \Throwable $throwable, $wpdb = null ) {
		if ( null === $wpdb ) {
			$wpdb = isset( $GLOBALS['wpdb'] ) ? $GLOBALS['wpdb'] : null;
		}

		if ( ! $wpdb ) {
			self::log( $option_name, 'unreadable and not repairable (no $wpdb)', $throwable );

			return [];
		}

		$clean = self::clean( self::readRaw( $option_name, $wpdb ), $dropped );

		$wpdb->update(
			$wpdb->options,
			[ 'option_value' => maybe_serialize( $clean ) ],
			[ 'option_name' => $option_name ]
		);

		wp_cache_delete( $option_name, 'options' );
		wp_cache_delete( 'alloptions', 'options' );

		self::log( $option_name, sprintf( 'repaired, %d unstorable display callback(s) dropped', $dropped ), $throwable );

		return $clean;
	}

	private static function readRaw( $option_name, $wpdb ) {
		$raw = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				$option_name
			)
		);

		if ( ! is_string( $raw ) || '' === $raw ) {
			return [];
		}

		$stored = @unserialize( $raw, [ 'allowed_classes' => [ \WPML_Notice::class ] ] );

		return is_array( $stored ) ? $stored : [];
	}

	private static function stripUnstorableCallbacks( \WPML_Notice $notice ) {
		$property = new \ReflectionProperty( \WPML_Notice::class, 'display_callbacks' );
		$property->setAccessible( true );

		$callbacks = $property->getValue( $notice );

		if ( ! is_array( $callbacks ) ) {
			$property->setValue( $notice, [] );

			return null === $callbacks ? 0 : 1;
		}

		$kept    = array_values( array_filter( $callbacks, [ self::class, 'isStorableCallback' ] ) );
		$dropped = count( $callbacks ) - count( $kept );

		if ( $dropped ) {
			$property->setValue( $notice, $kept );
		}

		return $dropped;
	}

	private static function log( $option_name, $outcome, \Throwable $throwable ) {
		error_log(
			sprintf(
				'WPML: notice store option "%s" could not be read (%s: %s) — %s',
				$option_name,
				get_class( $throwable ),
				$throwable->getMessage(),
				$outcome
			)
		);
	}
}
