<?php

namespace WPML\Request\Policy;

final class Registry {

	const AJAX         = 'ajax';
	const ADMIN_POST   = 'admin-post';
	const REST         = 'rest';
	const XMLRPC       = 'xmlrpc';
	const NETWORK      = 'network';
	const PSEUDO_ROUTE = 'pseudo-route';

	private static $declared = [];

	private static $listeners = [];

	private static $roots = [];

	private static $detached = [];

	public static function declare( $transport, $hook, Policy $policy ) {
		if ( $policy->isListener() ) {
			throw new \InvalidArgumentException( esc_html( "A listener policy cannot govern the WPML-owned hook '$hook'; use declareListener()." ) );
		}

		self::$declared[ (string) $transport ][ (string) $hook ] = $policy;

		return $policy;
	}

	public static function declareListener( $hook, $reason ) {
		$policy = Policy::listener( $reason );

		self::$listeners[ (string) $hook ] = $policy;

		return $policy;
	}

	public static function policyFor( $transport, $hook ) {
		return isset( self::$declared[ $transport ][ $hook ] ) ? self::$declared[ $transport ][ $hook ] : null;
	}

	public static function listenerPolicyFor( $hook ) {
		return isset( self::$listeners[ $hook ] ) ? self::$listeners[ $hook ] : null;
	}

	public static function declared( $transport ) {
		return isset( self::$declared[ $transport ] ) ? self::$declared[ $transport ] : [];
	}

	public static function listeners() {
		return self::$listeners;
	}

	public static function all() {
		return self::$declared;
	}

	public static function ownRoot( $path ) {
		foreach ( self::normalizeRoot( $path ) as $root ) {
			if ( '' !== $root && ! in_array( $root, self::$roots, true ) ) {
				self::$roots[] = $root;
			}
		}
	}

	public static function ownedRoots() {
		if ( [] === self::$roots ) {
			foreach ( [ 'WPML_PLUGIN_PATH', 'WPML_ST_PATH', 'WPML_MEDIA_PATH', 'WPML_CMS_NAV_PLUGIN_PATH', 'WPML_STICKY_LINKS_PATH' ] as $constant ) {
				if ( defined( $constant ) && is_string( constant( $constant ) ) && '' !== constant( $constant ) ) {
					self::ownRoot( constant( $constant ) );
				}
			}
		}

		return self::$roots;
	}

	public static function isOwnedCallback( $callback ) {
		$file = self::callbackFile( $callback );
		if ( '' === $file ) {
			return false;
		}

		foreach ( self::ownedRoots() as $root ) {
			if ( 0 === strpos( $file, $root ) ) {
				$inside = substr( $file, strlen( $root ) );
				if ( 0 === strpos( $inside, 'vendor/' ) && 0 !== strpos( $inside, 'vendor/wpml/' ) ) {
					return false;
				}

				return true;
			}
		}

		return false;
	}

	public static function callbackFile( $callback ) {
		try {
			if ( is_array( $callback ) && 2 === count( $callback ) ) {
				$reflection = new \ReflectionMethod( $callback[0], $callback[1] );
			} elseif ( is_string( $callback ) && false !== strpos( $callback, '::' ) ) {
				$reflection = new \ReflectionMethod( $callback );
			} elseif ( is_object( $callback ) && ! ( $callback instanceof \Closure ) ) {
				$reflection = new \ReflectionMethod( $callback, '__invoke' );
			} else {
				$reflection = new \ReflectionFunction( $callback );
			}
			$file = $reflection->getFileName();
		} catch ( \Throwable $e ) {
			return '';
		}

		return is_string( $file ) ? self::normalizePath( $file ) : '';
	}

	public static function describeCallback( $callback ) {
		if ( is_array( $callback ) && 2 === count( $callback ) ) {
			return ( is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0] ) . '::' . $callback[1];
		}
		if ( is_string( $callback ) ) {
			return $callback;
		}
		if ( $callback instanceof \Closure ) {
			return 'Closure@' . self::callbackFile( $callback );
		}
		if ( is_object( $callback ) ) {
			return get_class( $callback ) . '::__invoke';
		}

		return gettype( $callback );
	}

	public static function recordDetached( $hook, $description ) {
		self::$detached[ $hook . ' ' . $description ] = $description;
	}

	public static function detached() {
		return array_keys( self::$detached );
	}

	public static function reset() {
		self::$declared  = [];
		self::$listeners = [];
		self::$roots     = [];
		self::$detached  = [];
	}

	private static function normalizeRoot( $path ) {
		$roots = [ self::normalizePath( (string) $path ) ];
		$real  = realpath( (string) $path );
		if ( is_string( $real ) ) {
			$roots[] = self::normalizePath( $real );
		}

		return array_values(
			array_unique(
				array_map(
					function ( $root ) {
						return rtrim( $root, '/' ) . '/';
					},
					array_filter( $roots, 'strlen' )
				)
			)
		);
	}

	private static function normalizePath( $path ) {
		$path = str_replace( '\\', '/', $path );

		return preg_replace( '#/+#', '/', $path );
	}
}
