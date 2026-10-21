<?php

class WPML_ST_Translations_File_Components_Find_Theme implements WPML_ST_Translations_File_Components_Find {
	private $debug_backtrace;

	private $file;

	public function __construct( WPML_Debug_BackTrace $debug_backtrace, WPML_File $file ) {
		$this->debug_backtrace = $debug_backtrace;
		$this->file            = $file;
	}

	public static function get_theme_roots() {
		global $wp_theme_directories;

		$roots = array( rtrim( (string) get_theme_root(), '/\\' ) );
		if ( is_array( $wp_theme_directories ) ) {
			foreach ( $wp_theme_directories as $root ) {
				$roots[] = rtrim( (string) $root, '/\\' );
			}
		}

		$roots = array_values( array_unique( array_filter( $roots, 'strlen' ) ) );
		usort(
			$roots,
			function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);

		return $roots;
	}

	public function find_id( $file ) {
		return $this->find_theme_directory( $file );
	}

	private function find_theme_directory( $file ) {
		$theme_dir = $this->find_theme_root( $file );
		if ( null !== $theme_dir ) {
			return $this->extract_theme_directory( $file, $theme_dir );
		}

		return $this->find_theme_directory_in_backtrace();
	}

	private function find_theme_directory_in_backtrace() {
		$file = $this->find_file_in_backtrace();
		if ( ! $file ) {
			return null;
		}

		$theme_dir = $this->find_theme_root( $file );

		return null !== $theme_dir ? $this->extract_theme_directory( $file, $theme_dir ) : null;
	}

	private function find_file_in_backtrace() {
		$stack = $this->debug_backtrace->get_backtrace();

		foreach ( $stack as $call ) {
			if ( isset( $call['function'] ) && 'load_theme_textdomain' === $call['function'] ) {
				return $call['file'];
			}
		}

		return null;
	}

	private function find_theme_root( $file_path ) {
		$file_path = $this->file->fix_dir_separator( $file_path );

		foreach ( self::get_theme_roots() as $theme_root ) {
			$theme_root = rtrim( $this->file->fix_dir_separator( $theme_root ), '/\\' );
			if ( '' !== $theme_root
				&& ( $file_path === $theme_root || 0 === strpos( $file_path, $theme_root . DIRECTORY_SEPARATOR ) )
			) {
				return $theme_root;
			}
		}

		return null;
	}

	private function extract_theme_directory( $file_path, $theme_dir ) {
		$file_path = $this->file->fix_dir_separator( $file_path );
		$dir       = ltrim( substr( $file_path, strlen( $theme_dir ) ), DIRECTORY_SEPARATOR );
		$dir       = explode( DIRECTORY_SEPARATOR, $dir );
		$theme     = trim( $dir[0], DIRECTORY_SEPARATOR );

		if ( isset( $dir[1] ) && '' !== $theme && ! in_array( $dir[1], array( '', '.', '..' ), true )
			&& ! file_exists( $theme_dir . '/' . $theme . '/style.css' )
			&& file_exists( $theme_dir . '/' . $theme . '/' . $dir[1] . '/style.css' )
		) {
			return $theme . '/' . $dir[1];
		}

		return $theme;
	}
}
