<?php

class WPML_ST_Translations_File_Entry {

	const NOT_IMPORTED    = 'not_imported';
	const IMPORTED        = 'imported';
	const PARTLY_IMPORTED = 'partly_imported';
	const FINISHED        = 'finished';
	const SKIPPED         = 'skipped';

	const PATTERN_SEARCH_LANG_MO   = '#[-]?([a-z]+[_A-Z]*)\.mo$#i';
	const PATTERN_SEARCH_LANG_JSON = '#([a-z]+[_A-Z]*)-[-a-z0-9]+\.json$#i';

	private $path;

	private $domain;

	private $status;

	private $imported_strings_count = 0;

	private $last_modified;

	private $component_type;

	private $component_id;

	public function __construct( $path, $domain, $status = self::NOT_IMPORTED ) {
		if ( ! is_string( $path ) ) {
			throw new InvalidArgumentException( 'MO File path must be string type' );
		}
		if ( ! is_string( $domain ) ) {
			throw new InvalidArgumentException( 'MO File domain must be string type' );
		}

		$this->path   = self::to_stored_path( $path );
		$this->domain = $domain;

		$this->validate_status( $status );
		$this->status = $status;
	}

	public static function to_stored_path( $path, $abspath = null, $content_dir = null ) {
		list( $abspath, $content_dir ) = self::get_roots( $abspath, $content_dir );

		$path = self::fix_dir_separator( $path );
		if ( DIRECTORY_SEPARATOR === $abspath ) {
			return $path;
		}
		if ( 0 === strpos( $path, $abspath ) ) {
			return (string) substr( $path, strlen( $abspath ) );
		}
		if ( 0 === strpos( $path, $content_dir . DIRECTORY_SEPARATOR ) ) {
			return basename( $content_dir ) . substr( $path, strlen( $content_dir ) );
		}

		return $path;
	}

	public static function resolve_stored_path( $path, $abspath = null, $content_dir = null ) {
		list( $abspath, $content_dir ) = self::get_roots( $abspath, $content_dir );

		$path = self::fix_dir_separator( $path );
		if ( self::is_absolute_path( $path ) ) {
			return $path;
		}

		$content_prefix = basename( $content_dir ) . DIRECTORY_SEPARATOR;
		if ( 0 !== strpos( $content_dir, $abspath ) && 0 === strpos( $path, $content_prefix ) ) {
			return $content_dir . DIRECTORY_SEPARATOR . substr( $path, strlen( $content_prefix ) );
		}

		return $abspath . $path;
	}

	private static function get_roots( $abspath, $content_dir ) {
		$abspath     = self::fix_dir_separator( null === $abspath ? ABSPATH : $abspath );
		$content_dir = self::fix_dir_separator( null === $content_dir ? WP_CONTENT_DIR : $content_dir );

		return array(
			rtrim( $abspath, '/\\' ) . DIRECTORY_SEPARATOR,
			rtrim( $content_dir, '/\\' ),
		);
	}

	private static function is_absolute_path( $path ) {
		return 0 === strpos( $path, '/' ) || 0 === strpos( $path, '\\' ) || 1 === preg_match( '#^[A-Za-z]:[\\\\/]#', $path );
	}

	public function get_path() {
		return $this->path;
	}

	public function get_full_path() {
		return self::resolve_stored_path( $this->path );
	}

	public function get_path_hash() {
		return md5( $this->path );
	}

	public function get_domain() {
		return $this->domain;
	}

	public function get_status() {
		return $this->status;
	}

	public function set_status( $status ) {
		$this->validate_status( $status );
		$this->status = $status;
	}

	public function get_imported_strings_count() {
		return $this->imported_strings_count;
	}

	public function set_imported_strings_count( $imported_strings_count ) {
		$this->imported_strings_count = (int) $imported_strings_count;
	}

	public function get_last_modified() {
		return $this->last_modified;
	}

	public function set_last_modified( $last_modified ) {
		$this->last_modified = (int) $last_modified;
	}

	public function __get( $name ) {
		if ( in_array( $name, array( 'path', 'domain', 'status', 'imported_strings_count', 'last_modified' ), true ) ) {
			return $this->$name;
		}
		if ( $name === 'path_md5' ) {
			return $this->get_path_hash();
		}

		return null;
	}

	public function get_file_locale() {
		return \WPML\Container\make( WPML_ST_Translations_File_Locale::class )->get( $this->get_path(), $this->get_domain() );
	}

	public function get_component_type() {
		return $this->component_type;
	}

	public function set_component_type( $component_type ) {
		$this->component_type = $component_type;
	}

	public function get_component_id() {
		return $this->component_id;
	}

	public function set_component_id( $component_id ) {
		$this->component_id = $component_id;
	}

	private function validate_status( $status ) {
		$allowed_statuses = array(
			self::NOT_IMPORTED,
			self::IMPORTED,
			self::PARTLY_IMPORTED,
			self::FINISHED,
			self::SKIPPED,
		);

		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			throw new InvalidArgumentException( 'Status of MO file is invalid' );
		}
	}

	private static function fix_dir_separator( $path ) {
		return ( '\\' === DIRECTORY_SEPARATOR ) ? str_replace( '/', '\\', $path ) : str_replace( '\\', '/', $path );
	}

	public function get_extension() {
		return pathinfo( $this->path, PATHINFO_EXTENSION );
	}
}
