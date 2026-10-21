<?php

class WPML_Super_Globals_Validation {

	public function get( $key, $filter = FILTER_SANITIZE_FULL_SPECIAL_CHARS, $options = null ) {
		return $this->get_value( $key, $_GET, $filter, $options );
	}

	public function post( $key, $filter = FILTER_SANITIZE_FULL_SPECIAL_CHARS, $options = null ) {
		return $this->get_value( $key, $_POST, $filter, $options );
	}

	private function get_value( $key, array $var, $filter = FILTER_SANITIZE_FULL_SPECIAL_CHARS, $options = null ) {
		$value = null;

		if ( array_key_exists( $key, $var ) ) {
			$raw = wp_unslash( $var[ $key ] );

			if ( is_array( $raw ) ) {
				$value = $this->filter_each( $raw, $filter, $options );
			} elseif ( null !== $options ) {
				$value = filter_var( $raw, $filter, $options );
			} else {
				$value = filter_var( $raw, $filter, $this->get_default_flags( $filter ) );
			}
		}

		return $value;
	}

	private function get_default_flags( $filter ) {
		return FILTER_SANITIZE_FULL_SPECIAL_CHARS === $filter ? FILTER_FLAG_NO_ENCODE_QUOTES : 0;
	}

	private function filter_each( array $raw, $filter, $options ) {
		$flags  = null !== $options ? $options : $this->get_default_flags( $filter );
		$values = array();

		foreach ( $raw as $key => $item ) {
			$values[ $key ] = is_array( $item ) ? false : filter_var( $item, $filter, $flags );
		}

		return $values;
	}
}
