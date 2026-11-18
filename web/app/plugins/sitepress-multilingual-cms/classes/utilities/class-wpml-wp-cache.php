<?php

class WPML_WP_Cache {

	const KEYS = 'WPML_WP_Cache__group_keys';

	const GENERATION = 'WPML_WP_Cache__generation';

	private const EXPIRE = 86400;

	private $group;

	public function __construct( $group = '' ) {
		$this->group = $group;
	}

	public function get( $key, &$found = null ) {
		$generation = $this->generation();
		$value      = wp_cache_get( $key, $this->group, false, $found );
		if ( $this->is_entry( $value ) && $this->is_current( $value, $generation ) ) {
			$found = true;

			return $value['data'];
		}

		$found = false;

		return false;
	}

	public function get_multiple( array $keys ) {
		if ( ! $keys ) {
			return [];
		}

		$generation = $this->generation();
		$found      = [];
		foreach ( wp_cache_get_multiple( $keys, $this->group ) as $key => $value ) {
			if ( $this->is_entry( $value ) && $this->is_current( $value, $generation ) ) {
				$found[ $key ] = $value['data'];
			}
		}

		return $found;
	}

	public function set( $key, $data, $expire = self::EXPIRE ) {
		return wp_cache_set( $key, $this->entry( $data ), $this->group, $expire );
	}

	public function add( $key, $data, $expire = self::EXPIRE ) {
		$entry = $this->entry( $data );
		if ( wp_cache_add( $key, $entry, $this->group, $expire ) ) {
			return true;
		}

		$value = wp_cache_get( $key, $this->group, true );
		if ( $this->is_entry( $value ) && $this->is_current( $value, $entry['generation'] ) ) {
			return false;
		}

		return wp_cache_set( $key, $entry, $this->group, $expire );
	}

	public function delete( $key ) {
		return wp_cache_delete( $key, $this->group );
	}

	public function flush_group_cache( $force = false ) {
		wp_cache_set( self::GENERATION, $this->new_generation(), $this->group );

		wp_cache_delete( self::KEYS, $this->group );
	}

	public function execute_and_cache( $key, $callback, $expire = self::EXPIRE ) {
		list( $result, $found ) = $this->get_with_found( $key );
		if ( ! $found ) {
			$result = $callback();
			$this->set( $key, $result, $expire );
		}

		return $result;
	}

	public function get_with_found( $key ) {
		$found  = false;
		$result = $this->get( $key, $found );

		return [ $result, $found ];
	}

	private function generation() {
		$generation = wp_cache_get( self::GENERATION, $this->group );
		if ( self::is_generation( $generation ) ) {
			return $generation;
		}

		$generation = $this->new_generation();
		if ( wp_cache_add( self::GENERATION, $generation, $this->group ) ) {
			return $generation;
		}

		$current = wp_cache_get( self::GENERATION, $this->group, true );
		if ( self::is_generation( $current ) ) {
			return $current;
		}

		wp_cache_set( self::GENERATION, $generation, $this->group );

		return $generation;
	}

	private function entry( $data ) {
		return [
			'data'       => $data,
			'generation' => $this->generation(),
		];
	}

	private static function is_generation( $generation ) {
		return is_string( $generation ) && '' !== $generation;
	}

	private function is_entry( $value ) {
		return is_array( $value ) && array_key_exists( 'data', $value );
	}

	private function is_current( array $entry, $generation ) {
		return isset( $entry['generation'] ) && $entry['generation'] === $generation;
	}

	private function new_generation() {
		return uniqid( '', true );
	}
}
