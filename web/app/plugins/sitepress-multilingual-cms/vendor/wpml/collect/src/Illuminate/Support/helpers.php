<?php

use WPML\Collect\Support\Arr;
use WPML\Collect\Support\Collection;
use WPML\Collect\Support\HelperFunctions;

if ( ! class_exists( 'WPML\\Collect\\Support\\HelperFunctions', false ) ) {
	require_once __DIR__ . '/../../HelperFunctions.php';
}

if (! function_exists('wpml_collect')) {
	function wpml_collect( $value = null ) {
		return new Collection( $value );
	}
}

if (! function_exists('data_get')) {
	function data_get( $target, $key, $default = null ) {
		return HelperFunctions::dataGet( $target, $key, $default );
	}
}

if (! function_exists('value')) {
	function value( $value, ...$args ) {
		return HelperFunctions::value( $value, ...$args );
	}
}

if (! function_exists('with')) {
	function with( $object ) {
		return HelperFunctions::with( $object );
	}
}
