<?php

class WPML_Media_Attachment_By_URL {

	private $wpdb;

	private $url;

	private $language;

	private $media_attachment_by_url_query;

	const SIZE_SUFFIX_REGEXP = '/-([0-9]+)x([0-9]+)\.([a-z]{3,4})$/i';

	const CACHE_KEY_PREFIX = 'attachment-id-from-guid-';
	const CACHE_GROUP      = 'wpml-media-setup';
	const CACHE_EXPIRATION = 1800;

	public $cache_hit_flag = null;

	public function __construct(
		wpdb $wpdb,
			 $url,
			 $language,
		\WPML\Media\Classes\WPML_Media_Attachment_By_URL_Query $media_attachment_by_url_query
	) {
		$this->url                           = self::normalizeUrl( $url );
		$this->language                      = $language;
		$this->wpdb                          = $wpdb;
		$this->media_attachment_by_url_query = $media_attachment_by_url_query;
	}

	public static function normalizeUrl( $url ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return $url;
		}

		$url = self::decodePath( $url );

		if ( '/' !== $url[0] ) {
			return $url;
		}

		$uploads_dir = wp_get_upload_dir();
		$baseurl     = wp_parse_url( $uploads_dir['baseurl'] );

		if ( empty( $baseurl['scheme'] ) ) {
			return $url;
		}

		if ( 0 === strpos( $url, '//' ) ) {
			return $baseurl['scheme'] . ':' . $url;
		}

		if ( empty( $baseurl['host'] ) ) {
			return $url;
		}

		$port = isset( $baseurl['port'] ) ? ':' . $baseurl['port'] : '';

		return $baseurl['scheme'] . '://' . $baseurl['host'] . $port . $url;
	}

	public static function hasEncodedPath( $url ) {
		return is_string( $url ) && self::decodePath( $url ) !== $url;
	}

	private static function decodePath( $url ) {
		if ( false === strpos( $url, '%' ) ) {
			return $url;
		}

		$parts = explode( '?', $url, 2 );
		$path  = rawurldecode( $parts[0] );
		if ( ! preg_match( '//u', $path ) ) {
			return $url;
		}
		$parts[0] = $path;

		return implode( '?', $parts );
	}

	public static function encodePathLike( $reference, $url ) {
		if ( ! is_string( $url ) || ! self::hasEncodedPath( $reference ) || false !== strpos( $url, '%' ) ) {
			return $url;
		}

		$parts = explode( '?', $url, 2 );
		if ( preg_match( '#^([a-z][a-z0-9+.-]*:)?(//[^/]*)?(/.*)?$#i', $parts[0], $m ) ) {
			$path     = isset( $m[3] ) ? $m[3] : '';
			$encoded  = implode( '/', array_map( 'rawurlencode', explode( '/', $path ) ) );
			$parts[0] = ( isset( $m[1] ) ? $m[1] : '' ) . ( isset( $m[2] ) ? $m[2] : '' ) . $encoded;
		}

		return implode( '?', $parts );
	}

	public static function getUrl( $url ) {
		$url = preg_replace( self::SIZE_SUFFIX_REGEXP, '.$3', $url );

		return $url;
	}

	public static function getUrlNotScaled( $url ) {
		$url = preg_replace( self::SIZE_SUFFIX_REGEXP, '.$3', $url );
		$url = str_replace( '-scaled', '', $url );

		return $url;
	}

	public static function getUrlRelativePath( $url ) {
		$uploads_dir   = wp_get_upload_dir();
		$relative_path = ltrim( preg_replace( '@^' . preg_quote( $uploads_dir['baseurl'], '@' ) . '@', '', $url ), '/' );

		return $relative_path;
	}

	public static function getUrlRelativePathOriginal( $relative_path ) {
		return preg_replace( self::SIZE_SUFFIX_REGEXP, '.$3', $relative_path );
	}

	public static function getUrlRelativePathScaled( $url ) {
		$relative_path = self::getUrlRelativePath( $url );
		$relative_path = str_replace( '-scaled', '', $relative_path );
		$relative_path = preg_replace( '/(\.[^.]+)$/', '-scaled$1', $relative_path );

		return $relative_path;
	}

	public function get_id() {
		if ( ! $this->url ) {
			return 0;
		}

		$cache_key = self::CACHE_KEY_PREFIX . md5( $this->language . '#' . $this->url );

		$attachment_id = wp_cache_get( $cache_key, self::CACHE_GROUP, false, $this->cache_hit_flag );
		if ( ! $this->cache_hit_flag ) {
			$attachment_id = $this->get_id_from_guid();
			if ( ! $attachment_id ) {
				$attachment_id = $this->get_id_from_meta();
			}

			wp_cache_add( $cache_key, $attachment_id, self::CACHE_GROUP, self::CACHE_EXPIRATION );
		}

		return $attachment_id;
	}

	private function get_id_from_guid() {
		$attachment_id = $this->media_attachment_by_url_query->getIdFromGuid( $this->language, $this->url );
		if ( ! $attachment_id ) {
			$attachment_id = $this->media_attachment_by_url_query->getIdFromGuid( $this->language, self::getUrlNotScaled( $this->url ) );
		}

		return $attachment_id;
	}

	private function get_id_from_meta() {
		$relative_path        = self::getUrlRelativePath( $this->url );
		$relative_path_scaled = self::getUrlRelativePathScaled( $this->url );

		$attachment_id = $this->media_attachment_by_url_query->getIdFromMeta( $relative_path, $this->language );
		if ( ! $attachment_id ) {
			$attachment_id = $this->media_attachment_by_url_query->getIdFromMeta( $relative_path_scaled, $this->language );
		}

		if ( ! $attachment_id && preg_match( self::SIZE_SUFFIX_REGEXP, $relative_path ) ) {
			$attachment_id = $this->get_attachment_image_from_meta_fallback( $relative_path );
		}

		return $attachment_id;
	}

	private function get_attachment_image_from_meta_fallback( $relative_path ) {
		$attachment_id = null;

		$relative_path_original = self::getUrlRelativePathOriginal( $relative_path );
		$attachment_id_original = $this->media_attachment_by_url_query->getIdFromMeta( $relative_path_original, $this->language );

		if ( $attachment_id_original ) {
			$attachment_meta_data = wp_get_attachment_metadata( $attachment_id_original );
			if ( $this->validate_image_size( $relative_path, $attachment_meta_data ) ) {
				$attachment_id = $attachment_id_original;
			}
		}

		return $attachment_id;
	}

	private function validate_image_size( $path, $attachment_meta_data ) {
		$valid     = false;
		$file_name = basename( $path );

		foreach ( $attachment_meta_data['sizes'] as $size ) {
			if ( $file_name === $size['file'] ) {
				$valid = true;
				break;
			}
		}

		return $valid;
	}

}
