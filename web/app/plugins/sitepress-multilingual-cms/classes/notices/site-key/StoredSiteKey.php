<?php

namespace WPML\Notices\SiteKey;

class StoredSiteKey {

	const OPTION = 'wp_installer_settings';

	public static function read(): string {
		global $wpdb;

		if ( ! is_object( $wpdb ) ) {
			return '';
		}

		$row = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				self::OPTION
			)
		);

		$node = self::decode( $row );
		foreach ( [ 'repositories', 'wpml', 'subscription', 'key' ] as $level ) {
			if ( is_object( $node ) ) {
				$node = get_object_vars( $node );
			}

			if ( ! is_array( $node ) || ! isset( $node[ $level ] ) ) {
				return '';
			}

			$node = $node[ $level ];
		}

		return is_scalar( $node ) ? (string) $node : '';
	}

	public static function orStored( $cachedRead ): string {
		$cachedRead = is_scalar( $cachedRead ) ? (string) $cachedRead : '';

		return '' !== $cachedRead ? $cachedRead : self::read();
	}

	public static function forgetCachedCopies() {
		foreach ( [ 'alloptions', 'notoptions', self::OPTION, self::OPTION . '_common' ] as $cacheKey ) {
			wp_cache_delete( $cacheKey, 'options' );
		}
	}

	private static function decode( $row ) {
		if ( ! is_string( $row ) || '' === $row ) {
			return null;
		}

		if ( 0 === strpos( $row, 'a:' ) ) {
			$bytes = $row;
		} else {
			$bytes = base64_decode( $row, true );
			if ( false === $bytes ) {
				return null;
			}

			if ( strlen( $bytes ) > 1 && "\x78" === $bytes[0] && 0 === ( ( ord( $bytes[0] ) << 8 ) | ord( $bytes[1] ) ) % 31 ) {
				$bytes = function_exists( 'gzuncompress' ) ? @gzuncompress( $bytes ) : false;
				if ( false === $bytes ) {
					return null;
				}
			}
		}

		$settings = @unserialize( $bytes, [ 'allowed_classes' => [ 'stdClass' ] ] );

		return is_array( $settings ) || $settings instanceof \stdClass ? $settings : null;
	}
}
