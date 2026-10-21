<?php

class WPML_Language_Records {

	private $wpdb;

	private $languages;

	private $locale_lang_map;

	private $lang_locale_map;

	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function is_valid( $code ) {
		if ( ! $this->languages ) {
			$this->load();
		}

		return in_array( $code, $this->languages );
	}

	private function load() {
		$wpdb            = $this->wpdb;
		$this->languages = $this->wpdb->get_col( "SELECT code FROM {$wpdb->prefix}icl_languages" );
	}

	public function get_locale( $lang_code ) {
		$this->init_locale_lang_map();
		return isset( $this->lang_locale_map[ $lang_code ] ) ? $this->lang_locale_map[ $lang_code ] : null;
	}

	public function get_language_code( $locale ) {
		$this->init_locale_lang_map();
		return isset( $this->locale_lang_map[ $locale ] ) ? $this->locale_lang_map[ $locale ] : null;
	}

	private function init_locale_lang_map() {
		if ( null === $this->locale_lang_map ) {
			$this->locale_lang_map = array();
			$this->lang_locale_map = array();
			$active_owner          = array();

			$wpdb   = $this->wpdb;
			$rowset = $this->wpdb->get_results( "SELECT default_locale, code, active FROM {$wpdb->prefix}icl_languages" );

			foreach ( (array) $rowset as $row ) {
				$locale    = $row->default_locale ?: $row->code;
				$is_active = ! empty( $row->active );

				$this->lang_locale_map[ $row->code ] = $locale;

				if ( ! isset( $this->locale_lang_map[ $locale ] ) || ( $is_active && ! $active_owner[ $locale ] ) ) {
					$this->locale_lang_map[ $locale ] = $row->code;
					$active_owner[ $locale ]          = $is_active;
				}
			}
		}
	}

	public function get_locale_lang_map() {
		$this->init_locale_lang_map();
		return $this->locale_lang_map;
	}
}
