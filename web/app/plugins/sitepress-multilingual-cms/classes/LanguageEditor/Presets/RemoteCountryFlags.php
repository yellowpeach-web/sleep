<?php

namespace WPML\LanguageEditor\Presets;

class RemoteCountryFlags {

	const UPLOADS_SUBDIR = 'flags';
	const MAX_SVG_BYTES  = 102400;

	private $wpdb;

	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public static function create() {
		global $wpdb;

		return new self( $wpdb );
	}

	public function apply( array $data ) {
		$wpdb  = $this->wpdb;
		$table = $wpdb->prefix . 'icl_countries';
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			return false;
		}

		$dir = $this->uploadsDir();
		if ( '' === $dir ) {
			return false;
		}

		$flagless = (array) $wpdb->get_col( "SELECT code FROM `{$table}` WHERE flag IS NULL OR flag = ''" );
		if ( ! $flagless ) {
			return 0;
		}

		$bySvgKey = [];
		foreach ( $data as $cc => $svg ) {
			$bySvgKey[ strtoupper( trim( (string) $cc ) ) ] = $svg;
		}

		$applied = 0;
		foreach ( $flagless as $code ) {
			$cc = strtoupper( trim( (string) $code ) );
			if ( ! isset( $bySvgKey[ $cc ] ) || ! self::isValidSvg( $bySvgKey[ $cc ] ) ) {
				continue;
			}

			$basename = strtolower( $cc ) . '.country.svg';
			if ( ! $this->store( $dir . $basename, (string) $bySvgKey[ $cc ] ) ) {
				continue;
			}

			$updated = $wpdb->query(
				$wpdb->prepare(
					"UPDATE `{$table}` SET flag = %s WHERE code = %s AND ( flag IS NULL OR flag = '' )",
					$basename,
					$cc
				)
			);
			if ( $updated ) {
				++$applied;
			}
		}

		return $applied;
	}

	public static function resolveUrl( $flag ) {
		$flag = (string) $flag;
		if ( '' === $flag || false !== strpos( $flag, '/' ) || false !== strpos( $flag, ':' ) ) {
			return $flag;
		}

		$upload = wp_upload_dir();
		if ( empty( $upload['basedir'] ) || empty( $upload['baseurl'] ) ) {
			return $flag;
		}

		$file = trailingslashit( $upload['basedir'] ) . self::UPLOADS_SUBDIR . '/' . $flag;
		if ( ! file_exists( $file ) ) {
			return $flag;
		}

		return trailingslashit( $upload['baseurl'] ) . self::UPLOADS_SUBDIR . '/' . $flag;
	}

	public static function isValidSvg( $svg ) {
		if ( ! is_string( $svg ) || '' === trim( $svg ) || strlen( $svg ) > self::MAX_SVG_BYTES ) {
			return false;
		}

		$body = ltrim( $svg );
		if ( 0 === strpos( $body, '<?xml' ) ) {
			$body = ltrim( (string) preg_replace( '/^<\?xml[^>]*\?>/', '', $body ) );
		}

		if ( 0 !== strpos( $body, '<svg' ) || false === stripos( $body, '</svg>' ) ) {
			return false;
		}

		return false === stripos( $body, '<script' ) && false === stripos( $body, 'javascript:' );
	}

	private function store( $path, $svg ) {
		if ( file_exists( $path ) && (string) file_get_contents( $path ) === $svg ) {
			return true;
		}

		$dir = dirname( $path );
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		return false !== file_put_contents( $path, $svg );
	}

	private function uploadsDir() {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) || empty( $upload['basedir'] ) ) {
			return '';
		}

		return trailingslashit( $upload['basedir'] ) . self::UPLOADS_SUBDIR . '/';
	}
}
