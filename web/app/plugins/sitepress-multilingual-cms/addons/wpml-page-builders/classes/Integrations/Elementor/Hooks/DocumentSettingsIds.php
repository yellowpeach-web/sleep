<?php

namespace WPML\PB\Elementor\Hooks;

class DocumentSettingsIds implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const COPIED_VALUE_HOOK = 'wpml_sync_custom_field_copied_value';

	const BACKGROUND_IMAGE_KEY = '/^(overlay_)?background_image(_[a-z0-9_]+)?$/';

	const URL_ACTIONS = [ 'show', 'hide' ];

	private static $pins = 0;

	private static $attached = false;

	public function add_hooks() {
		add_filter( 'wpml_sync_custom_fields_batch_writer_enabled', [ self::class, 'onBatchWriterEnabled' ], 10, 4 );
		add_filter( 'wpml_pb_copy_meta_field', [ self::class, 'onPbCopy' ], 10, 4 );
		add_action( 'shutdown', [ self::class, 'onShutdown' ] );
	}

	public static function enable() {
		self::$pins++;
		self::attach();
	}

	public static function disable() {
		if ( self::$pins > 0 ) {
			self::$pins--;
		}

		if ( 0 === self::$pins ) {
			self::detach();
		}
	}

	public static function onShutdown() {
		self::$pins = 0;
		self::detach();
	}

	public static function onBatchWriterEnabled( $enabled, $postIdFrom, $postIdTo, $metaKeys ) {
		if ( self::pairCarriesOurKeys( (int) $postIdFrom, $metaKeys ) ) {
			self::attach();
		} elseif ( 0 === self::$pins ) {
			self::detach();
		}

		return $enabled;
	}

	public static function onSyncCopy( $value, $postIdFrom, $postIdTo, $metaKey ) {
		$ownValue = DocumentSettingsSync::ownValueOfNativeEditorTranslation( (int) $postIdTo, (string) $metaKey );
		if ( $ownValue ) {
			return $ownValue[0];
		}

		return self::convertFor( $value, (int) $postIdTo, (string) $metaKey );
	}

	public static function onPbCopy( $value, $postIdTo, $postIdFrom, $metaKey ) {
		return self::convertFor( $value, (int) $postIdTo, (string) $metaKey );
	}

	public static function convert( $value, $metaKey, $lang ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		if ( DocumentSettingsSync::PAGE_SETTINGS === $metaKey ) {
			return self::convertPageSettings( $value, $lang );
		}

		if ( DocumentSettingsSync::POPUP_DISPLAY_SETTINGS === $metaKey ) {
			return self::convertDisplaySettings( $value, $lang );
		}

		return $value;
	}

	public static function translateUrl( $url, $lang ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host || ! is_string( $home ) || strtolower( $host ) !== strtolower( $home ) ) {
			return $url;
		}

		$cut    = strcspn( $url, '?#' );
		$base   = substr( $url, 0, $cut );
		$suffix = substr( $url, $cut );

		$postId = (int) url_to_postid( $base );
		if ( $postId <= 0 ) {
			return $url;
		}

		$translatedId = self::translatedPostId( $postId, $lang );
		if ( ! $translatedId ) {
			return $url;
		}

		do_action( 'wpml_switch_language', $lang );
		try {
			$translatedUrl = get_permalink( $translatedId );
		} finally {
			do_action( 'wpml_switch_language', null );
		}
		if ( ! is_string( $translatedUrl ) || '' === $translatedUrl ) {
			return $url;
		}

		$translatedUrl = apply_filters( 'wpml_permalink', $translatedUrl, $lang );
		if ( ! is_string( $translatedUrl ) || '' === $translatedUrl ) {
			return $url;
		}

		return $translatedUrl . $suffix;
	}

	private static function attach() {
		if ( ! self::$attached ) {
			add_filter( self::COPIED_VALUE_HOOK, [ self::class, 'onSyncCopy' ], 10, 4 );
			self::$attached = true;
		}
	}

	private static function detach() {
		if ( self::$attached ) {
			remove_filter( self::COPIED_VALUE_HOOK, [ self::class, 'onSyncCopy' ], 10 );
			self::$attached = false;
		}
	}

	private static function pairCarriesOurKeys( $postIdFrom, $metaKeys ) {
		if ( ! is_array( $metaKeys ) || ! array_intersect( DocumentSettingsSync::KEYS, $metaKeys ) ) {
			return false;
		}

		foreach ( DocumentSettingsSync::KEYS as $metaKey ) {
			if ( metadata_exists( 'post', $postIdFrom, $metaKey ) ) {
				return true;
			}
		}

		return false;
	}

	private static function convertFor( $value, $postIdTo, $metaKey ) {
		if ( ! is_array( $value ) || ! in_array( $metaKey, DocumentSettingsSync::KEYS, true ) ) {
			return $value;
		}

		$details = apply_filters( 'wpml_post_language_details', null, $postIdTo );
		$lang    = is_array( $details ) && isset( $details['language_code'] ) ? $details['language_code'] : null;
		if ( ! is_string( $lang ) || '' === $lang ) {
			return $value;
		}

		return self::convert( $value, $metaKey, $lang );
	}

	private static function convertPageSettings( array $settings, $lang ) {
		foreach ( $settings as $key => $media ) {
			if ( is_string( $key ) && is_array( $media ) && preg_match( self::BACKGROUND_IMAGE_KEY, $key ) ) {
				$settings[ $key ] = self::convertMedia( $media, $lang );
			}
		}

		if ( isset( $settings['preview_id'] ) ) {
			$previewId = self::positiveId( $settings['preview_id'] );
			if ( $previewId ) {
				$translatedId = self::translatedPostId( $previewId, $lang );
				if ( $translatedId ) {
					$settings['preview_id'] = self::sameType( $settings['preview_id'], $translatedId );
				}
			}
		}

		return $settings;
	}

	private static function convertMedia( array $media, $lang ) {
		$id = isset( $media['id'] ) ? self::positiveId( $media['id'] ) : null;
		if ( ! $id ) {
			return $media;
		}

		$translatedId = self::positiveId( apply_filters( 'wpml_object_id', $id, 'attachment', false, $lang ) );
		if ( ! $translatedId || $translatedId === $id ) {
			return $media;
		}

		$media['id'] = self::sameType( $media['id'], $translatedId );

		$url = wp_get_attachment_url( $translatedId );
		if ( is_string( $url ) && '' !== $url ) {
			$media['url'] = $url;
		}

		return $media;
	}

	private static function convertDisplaySettings( array $settings, $lang ) {
		$timing = isset( $settings['timing'] ) && is_array( $settings['timing'] ) ? $settings['timing'] : null;
		if (
			! $timing
			|| 'yes' !== ( $timing['url'] ?? null )
			|| ! in_array( $timing['url_action'] ?? 'show', self::URL_ACTIONS, true )
			|| ! isset( $timing['url_url'] )
			|| ! is_string( $timing['url_url'] )
			|| '' === $timing['url_url']
		) {
			return $settings;
		}

		$settings['timing']['url_url'] = self::translateUrl( $timing['url_url'], $lang );

		return $settings;
	}

	private static function translatedPostId( $postId, $lang ) {
		$postType = get_post_type( $postId );
		if ( ! is_string( $postType ) || '' === $postType ) {
			return null;
		}

		$translatedId = self::positiveId( apply_filters( 'wpml_object_id', $postId, $postType, false, $lang ) );

		return $translatedId && $translatedId !== $postId ? $translatedId : null;
	}

	private static function positiveId( $id ) {
		if ( ! is_int( $id ) && ! is_string( $id ) ) {
			return null;
		}

		$id = filter_var( $id, FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1 ] ] );

		return false === $id ? null : $id;
	}

	private static function sameType( $stored, $id ) {
		return is_int( $stored ) ? $id : (string) $id;
	}
}
