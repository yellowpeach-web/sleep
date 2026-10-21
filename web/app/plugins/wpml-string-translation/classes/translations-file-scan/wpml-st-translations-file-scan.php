<?php

class WPML_ST_Translations_File_Scan {

	private $charset_filter_factory;

	private $plural_forms = array();

	public function __construct( WPML_ST_Translations_File_Scan_Db_Charset_Filter_Factory $charset_filter_factory ) {
		$this->charset_filter_factory = $charset_filter_factory;
	}

	public function can_read( $file ) {
		return is_readable( $file ) && is_file( $file );
	}

	public function load_translations( $file ) {
		if ( ! file_exists( $file ) ) {
			return array();
		}

		$translations = array();
		$file_type    = pathinfo( $file, PATHINFO_EXTENSION );

		$translations_file = null;
		switch ( $file_type ) {
			case 'mo':
				$translations_file = new WPML_ST_Translations_File_MO( $file );
				break;

			case 'json':
				$translations_file = new WPML_ST_Translations_File_JED( $file );
				break;
		}

		if ( $translations_file ) {
			$translations                = $translations_file->get_translations();
			$this->plural_forms[ $file ] = $translations_file->get_plural_forms();
		}

		$unicode_characters_filter = $this->charset_filter_factory->create();
		if ( $unicode_characters_filter ) {
			$translations = $unicode_characters_filter->filter( $translations );
		}

		return $translations;
	}

	public function get_plural_forms( $file ) {
		return isset( $this->plural_forms[ $file ] ) ? $this->plural_forms[ $file ] : null;
	}
}
