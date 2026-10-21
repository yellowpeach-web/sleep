<?php

namespace WPML\Media\Lookup;

class MediaLookupServiceFactory {

	private static $table;

	private static $service;

	const DEFAULT_TOMBSTONE_TTL = HOUR_IN_SECONDS;

	public static function service() {
		if ( ! isset( $GLOBALS['wpdb'] ) || ! $GLOBALS['wpdb'] instanceof \wpdb ) {
			return null;
		}

		if ( ! self::$service ) {
			global $wpdb;

			$enabled = (bool) apply_filters( 'wpml_media_url_lookup_enabled', true );

			$ttl = (int) apply_filters( 'wpml_media_url_lookup_tombstone_ttl', self::DEFAULT_TOMBSTONE_TTL );

			self::$service = new MediaLookupService(
				new MediaLookupSchema( $wpdb ),
				self::table(),
				new MediaLookupHasher(),
				new MediaLookupVerifier(),
				$ttl,
				$enabled
			);
		}

		return self::$service;
	}

	public static function table() {
		if ( ! self::$table ) {
			global $wpdb;
			self::$table = new MediaLookupTable( $wpdb, new MediaLookupSchema( $wpdb ), new MediaLookupHasher() );
		}

		return self::$table;
	}

	public static function hooksLoader() {
		global $wpdb;

		return new HooksLoader(
			new AttachmentSync( $wpdb, self::table(), new MediaLookupHasher() )
		);
	}

	public static function schema() {
		global $wpdb;

		return new MediaLookupSchema( $wpdb );
	}

	public static function hasher() {
		return new MediaLookupHasher();
	}

	public static function resetInstances() {
		self::$table   = null;
		self::$service = null;
	}
}
