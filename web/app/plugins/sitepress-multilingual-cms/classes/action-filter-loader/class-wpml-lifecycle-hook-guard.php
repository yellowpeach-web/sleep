<?php

use function WPML\PHP\Logger\error as logError;

class WPML_Lifecycle_Hook_Guard {

	const REQUEST_ENTRY_HOOKS = array(
		'plugins_loaded',
		'init',
		'wp_loaded',
		'admin_init',
		'parse_request',
		'wp',
		'template_redirect',
		'wpmuadminedit',
	);

	public static function allowed_classes() {
		return array(
			'WPML\\Core\\REST\\RewriteRules' => 'init: adds the option_rewrite_rules filter that maps the REST prefix under a language directory; registration only, reads nothing from the request, writes nothing',
			'WPML\\BlockEditor\\Loader'      => 'init: register_block_type() of the WPML blocks and their styles for every rendering context; registration only, reads nothing from the request, writes nothing',
		);
	}

	public static function is_allowed_loader( $loader ) {
		return array_key_exists( ltrim( (string) $loader, '\\' ), self::allowed_classes() );
	}

	public static function snapshot() {
		$snapshot = array();

		foreach ( self::REQUEST_ENTRY_HOOKS as $hook ) {
			$snapshot[ $hook ] = array();
			foreach ( self::callbacks_on( $hook ) as $priority => $callbacks ) {
				foreach ( $callbacks as $id => $entry ) {
					$snapshot[ $hook ][ $priority . ':' . $id ] = true;
				}
			}
		}

		return $snapshot;
	}

	public static function detach_added_since( array $snapshot, $loader ) {
		$detached = array();

		foreach ( self::REQUEST_ENTRY_HOOKS as $hook ) {
			$known = isset( $snapshot[ $hook ] ) ? $snapshot[ $hook ] : array();

			foreach ( self::callbacks_on( $hook ) as $priority => $callbacks ) {
				foreach ( $callbacks as $id => $entry ) {
					if ( isset( $known[ $priority . ':' . $id ] ) || ! isset( $entry['function'] ) ) {
						continue;
					}

					remove_action( $hook, $entry['function'], $priority );

					$description = self::describe( $entry['function'] );
					$detached[]  = $hook . ' => ' . $description;
					logError(
						sprintf(
							'WPML action loader: detached %s from %s - a class loaded under the REST loader group may not bind a request-entry lifecycle hook (loader %s, wpmldev-7978). Register the operation through a request adapter instead.',
							$description,
							$hook,
							$loader
						)
					);
				}
			}
		}

		return $detached;
	}

	private static function callbacks_on( $hook ) {
		global $wp_filter;

		if ( ! isset( $wp_filter[ $hook ] ) ) {
			return array();
		}
		$entry = $wp_filter[ $hook ];
		if ( is_object( $entry ) && isset( $entry->callbacks ) && is_array( $entry->callbacks ) ) {
			return $entry->callbacks;
		}

		return is_array( $entry ) ? $entry : array();
	}

	private static function describe( $callback ) {
		if ( is_array( $callback ) && 2 === count( $callback ) ) {
			return ( is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0] ) . '::' . $callback[1];
		}
		if ( is_string( $callback ) ) {
			return $callback;
		}
		if ( $callback instanceof Closure ) {
			return 'Closure';
		}

		return is_object( $callback ) ? get_class( $callback ) . '::__invoke' : gettype( $callback );
	}
}
