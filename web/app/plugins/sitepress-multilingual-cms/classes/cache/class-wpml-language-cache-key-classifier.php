<?php

namespace WPML\Language;

class CacheKeyClassifier {

	const LANGUAGE_DETAILS_PREFIX      = 'language_details_';
	const ALL_LANGUAGE_PREFIX          = 'all_language_';
	const IN_LANGUAGE_PREFIX           = 'in_language_';
	const SUPPORTED_LANGUAGE_CODES_KEY = 'supported_language_codes';

	private $active_languages_provider;

	private $active_language_codes;

	private $active_self_pairs;

	private $active_language_lookup;

	public function __construct( callable $active_languages_provider ) {
		$this->active_languages_provider = $active_languages_provider;
	}

	public function is_cold( $key ) {
		if ( ! is_string( $key ) || '' === $key ) {
			return false;
		}

		if ( self::SUPPORTED_LANGUAGE_CODES_KEY === $key ) {
			return false;
		}

		if ( 0 === strpos( $key, self::LANGUAGE_DETAILS_PREFIX ) ) {
			return $this->is_cold_language_details( $key );
		}

		if ( 0 === strpos( $key, self::ALL_LANGUAGE_PREFIX ) ) {
			return $this->is_cold_all_language( $key );
		}

		return false;
	}

	public function get_shard_display_language( $key ) {
		if ( ! is_string( $key ) || 0 !== strpos( $key, self::IN_LANGUAGE_PREFIX ) ) {
			return null;
		}

		$display_language = $this->get_structured_display_code( $key, self::IN_LANGUAGE_PREFIX );
		if ( null === $display_language ) {
			return null;
		}

		$active_languages = $this->get_active_language_lookup();

		return isset( $active_languages[ $display_language ] ) ? $display_language : null;
	}

	private function is_cold_language_details( $key ) {
		$self_pairs = $this->get_active_self_pairs();

		if ( ! $self_pairs ) {
			return false;
		}

		$suffix = substr( $key, strlen( self::LANGUAGE_DETAILS_PREFIX ) );

		return ! isset( $self_pairs[ $suffix ] );
	}

	private function is_cold_all_language( $key ) {
		$display_language = $this->get_all_language_display_code( $key );

		return null !== $display_language;
	}

	private function get_all_language_display_code( $key ) {
		return $this->get_structured_display_code( $key, self::ALL_LANGUAGE_PREFIX );
	}

	private function get_structured_display_code( $key, $prefix ) {
		$rest               = substr( $key, strlen( $prefix ) );
		$separator_position = false;
		$separator_length   = 0;

		foreach ( array( '__', '_0_', '_1_' ) as $separator ) {
			$position = strrpos( $rest, $separator );
			if ( false !== $position && ( false === $separator_position || $position > $separator_position ) ) {
				$separator_position = $position;
				$separator_length   = strlen( $separator );
			}
		}

		if (
			false === $separator_position
			|| 0 === $separator_position
			|| '' === substr( $rest, $separator_position + $separator_length )
		) {
			return null;
		}

		return substr( $rest, 0, $separator_position );
	}

	private function get_active_self_pairs() {
		$codes = array_values( array_map( 'strval', (array) call_user_func( $this->active_languages_provider ) ) );

		if ( $codes !== $this->active_language_codes ) {
			$this->active_language_codes  = $codes;
			$this->active_self_pairs      = array();
			$this->active_language_lookup = array();

			foreach ( $codes as $code ) {
				$this->active_self_pairs[ $code . $code ] = true;
				$this->active_language_lookup[ $code ]    = true;
			}
		}

		return $this->active_self_pairs;
	}

	private function get_active_language_lookup() {
		$this->get_active_self_pairs();

		return $this->active_language_lookup;
	}
}

class_alias( CacheKeyClassifier::class, 'WPML_Language_Cache_Key_Classifier' );
