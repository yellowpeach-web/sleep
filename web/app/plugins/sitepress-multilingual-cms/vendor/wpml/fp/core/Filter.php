<?php

namespace WPML\FP\System;

use WPML\Collect\Support\Collection;

class _Filter {

	private $key;
	private $fn;
	private $default;

	public function __construct( $key ) {
		$this->key = $key;
	}

	public function using( callable $fn ) {
		$this->fn = $fn;

		return $this;
	}

	public function defaultTo( $default ) {
		$this->default = $default;

		return $this;
	}

	public function __invoke( Collection $collection ) {
		$default = $this->default;
		$value   = $collection->has( $this->key )
			? call_user_func( $this->fn, $collection->get( $this->key ) )
			: ( $default instanceof \Closure ? $default() : $default );

		return $collection->put( $this->key, $value );
	}
}
