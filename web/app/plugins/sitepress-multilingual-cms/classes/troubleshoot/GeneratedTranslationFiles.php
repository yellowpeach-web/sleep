<?php

namespace WPML\Troubleshooting;

class GeneratedTranslationFiles {

	const SUB_DIRECTORY = 'wpml';

	const FILE_PATTERN = '/\.(mo|json)$|\.l10n\.php$/';

	private $lang_dir;

	private $filesystem;

	public function __construct( $lang_dir = null, ?callable $filesystem = null ) {
		$this->lang_dir   = null !== $lang_dir ? $lang_dir : ( defined( 'WP_LANG_DIR' ) ? WP_LANG_DIR : null );
		$this->filesystem = $filesystem ?: function () {
			return function_exists( 'wpml_get_filesystem' ) ? wpml_get_filesystem() : null;
		};
	}

	public function remove( $blog_id ) {
		if ( ! $this->lang_dir ) {
			return;
		}

		try {
			$filesystem = call_user_func( $this->filesystem );
			$directory  = self::directory( $this->lang_dir, (int) $blog_id );

			if ( ! $filesystem instanceof \WP_Filesystem_Base || ! $filesystem->is_dir( $directory ) ) {
				return;
			}

			foreach ( (array) $filesystem->dirlist( $directory, false ) as $name => $entry ) {
				if ( isset( $entry['type'] ) && 'f' === $entry['type'] && preg_match( self::FILE_PATTERN, (string) $name ) ) {
					$filesystem->delete( $directory . '/' . $name );
				}
			}
		} catch ( \Throwable $e ) {
			return;
		}
	}

	public static function directory( $lang_dir, $blog_id ) {
		$directory = rtrim( $lang_dir, '/\\' ) . '/' . self::SUB_DIRECTORY;

		return $blog_id > 1 ? $directory . '/' . $blog_id : $directory;
	}
}
