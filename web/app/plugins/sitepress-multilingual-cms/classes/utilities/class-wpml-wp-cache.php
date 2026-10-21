<?php

class WPML_WP_Cache {

	const KEYS = 'WPML_WP_Cache__group_keys';

	const GENERATION = 'WPML_WP_Cache__generation';

	private $group;

	public function __construct( $group = '' ) {
		$this->group = $group;
	}

	public function get( $key, &$found = null ) {
		$generation = $this->generation();
		$value      = wp_cache_get( $key, $this->group, false, $found );
		if (
			is_array( $value )
			&& array_key_exists( 'data', $value )
			&& isset( $value['generation'] )
			&& $value['generation'] === $generation
		) {
			$found = true;

			return $value['data'];
		}

		$found = false;

		return false;
	}

	public function set( $key, $data, $expire = 0 ) {
		return wp_cache_set(
			$key,
			[
				'data'       => $data,
				'generation' => $this->generation(),
			],
			$this->group,
			$expire
		);
	}

	public function delete( $key ) {
		return wp_cache_delete( $key, $this->group );
	}

	public function flush_group_cache( $force = false ) {
		wp_cache_set( self::GENERATION, $this->new_generation(), $this->group );

		wp_cache_delete( self::KEYS, $this->group );
	}

	public function execute_and_cache( $key, $callback, $expire = 0 ) {
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
		if ( is_string( $generation ) && '' !== $generation ) {
			return $generation;
		}

		$generation = $this->new_generation();
		wp_cache_set( self::GENERATION, $generation, $this->group );

		return $generation;
	}

	private function new_generation() {
		return uniqid( '', true );
	}
}
