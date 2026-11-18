<?php

namespace WPML\Templates;

class TwigCacheState {

	const ON = 'on';
	const ON_NOT_YET = 'on-not-yet';
	const ON_CANNOT_WRITE = 'on-cannot-write';
	const OFF_UNTIL = 'off-until';
	const OFF_UNRECORDED = 'off-unrecorded';
	const OFF_LEGACY = 'off-legacy';

	const REASON_NO_FOLDER = 'no-folder';
	const REASON_WRITE_FAILED = 'write-failed';

	const DETAIL_MAX_LENGTH = 200;

	private $wp_api;

	public function __construct( $wp_api = null ) {
		$this->wp_api = $wp_api ?: new \WPML_WP_API();
	}

	public function read() {
		$reading = array(
			'state'         => self::ON,
			'path'          => '',
			'uploads_error' => '',
			'reason'        => '',
			'detail'        => '',
			'until'         => 0,
		);

		if ( (bool) get_option( \WPML_Templates_Factory::OTGS_TWIG_CACHE_DISABLED_KEY, false ) ) {
			$reading['state'] = self::OFF_LEGACY;

			return $reading;
		}

		$off = get_transient( \WPML_Templates_Factory::TWIG_CACHE_UNWRITABLE_TRANSIENT );
		if ( false !== $off ) {
			if ( is_array( $off ) && isset( $off['reason'] ) && in_array( $off['reason'], array( self::REASON_NO_FOLDER, self::REASON_WRITE_FAILED ), true ) ) {
				$reading['state']  = self::OFF_UNTIL;
				$reading['reason'] = $off['reason'];
				$reading['detail'] = isset( $off['detail'] ) ? (string) $off['detail'] : '';
				$reading['until']  = isset( $off['until'] ) ? (int) $off['until'] : 0;
			} else {
				$reading['state'] = self::OFF_UNRECORDED;
			}

			return $reading;
		}

		$folder                   = $this->folder();
		$reading['path']          = $folder['path'];
		$reading['uploads_error'] = $folder['uploads_error'];
		$reading['state']         = $this->folder_state( $folder['path'] );

		return $reading;
	}

	public function missing_folder_detail() {
		$folder = $this->folder();

		return '' !== $folder['uploads_error'] ? $folder['uploads_error'] : $folder['path'];
	}

	public static function shorten_detail( $detail ) {
		return mb_substr( (string) $detail, 0, self::DETAIL_MAX_LENGTH );
	}

	private function folder() {
		$root = $this->wp_api->constant( 'WPML_CACHE_PATH_ROOT' );
		if ( $root ) {
			return array(
				'path'          => trailingslashit( (string) $root ) . \WPML_Cache_Directory::MAIN_DIRECTORY_NAME . '/twig/',
				'uploads_error' => '',
			);
		}

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return array(
				'path'          => '',
				'uploads_error' => (string) $upload_dir['error'],
			);
		}

		return array(
			'path'          => trailingslashit( $upload_dir['basedir'] ) . 'cache/' . \WPML_Cache_Directory::MAIN_DIRECTORY_NAME . '/twig/',
			'uploads_error' => '',
		);
	}

	private function folder_state( $path ) {
		if ( '' === $path ) {
			return self::ON_CANNOT_WRITE;
		}

		if ( @is_dir( $path ) ) {
			return wp_is_writable( $path ) ? self::ON : self::ON_CANNOT_WRITE;
		}

		$parent = untrailingslashit( $path );
		do {
			$above  = dirname( $parent );
			$at_top = $above === $parent;
			$parent = $above;
		} while ( ! $at_top && ! @is_dir( $parent ) );

		return ! $at_top && wp_is_writable( $parent ) ? self::ON_NOT_YET : self::ON_CANNOT_WRITE;
	}
}
