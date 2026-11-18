<?php

namespace WPML\Collect\Support\Traits;

use BadMethodCallException;
use Closure;

trait Macroable {

	protected static $macros = [];

	public static function macro( $name, callable $macro ) {
		static::$macros[ $name ] = $macro;
	}

	public static function hasMacro( $name ) {
		return isset( static::$macros[ $name ] );
	}

	public static function __callStatic( $method, $parameters ) {
		if ( ! static::hasMacro( $method ) ) {
			throw new BadMethodCallException( "Method {$method} does not exist." );
		}

		$macro = static::$macros[ $method ];

		if ( $macro instanceof Closure ) {
			$macro = Closure::bind( $macro, null, static::class );
		}

		return call_user_func_array( $macro, $parameters );
	}

	public function __call( $method, $parameters ) {
		if ( ! static::hasMacro( $method ) ) {
			throw new BadMethodCallException( "Method {$method} does not exist." );
		}

		$macro = static::$macros[ $method ];

		if ( $macro instanceof Closure ) {
			$macro = $macro->bindTo( $this, static::class );
		}

		return call_user_func_array( $macro, $parameters );
	}
}
