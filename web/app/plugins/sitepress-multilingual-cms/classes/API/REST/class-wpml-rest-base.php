<?php

abstract class WPML_REST_Base {
	const CAPABILITY_EXTERNAL = 'external';

	const REST_NAMESPACE = 'wpml/v1';

	const SCALAR_TYPES = array( 'string', 'integer', 'number', 'boolean' );

	const SCALAR_CALLBACK_CLASSES = array(
		'WPML_REST_Arguments_Validation',
		'WPML_REST_Arguments_Sanitation',
	);

	const LIST_CALLBACKS = array( 'is_array', 'array_of_integers' );
	protected $namespace;

	public function __construct( $namespace = null ) {
		if ( ! $namespace ) {
			$namespace = self::REST_NAMESPACE;
		}
		$this->namespace = $namespace;
	}

	abstract public function add_hooks();

	public function validate_permission( WP_REST_Request $request ) {
		$user_can = $this->user_has_matching_capabilities( $request );

		if ( ! $user_can ) {
			return false;
		}

		$nonce = $this->get_nonce( $request );

		return $user_can && wp_verify_nonce( $nonce, 'wp_rest' );
	}

	abstract public function get_allowed_capabilities( WP_REST_Request $request );

	protected function register_route( $route, array $args ) {
		$args = $this->ensure_permission( $route, $args );
		$args = self::ensure_scalar_args( $args );

		register_rest_route( $this->namespace, $route, $args );
	}

	private static function ensure_scalar_args( array $args ) {
		if ( ! isset( $args['args'] ) || ! is_array( $args['args'] ) ) {
			return $args;
		}

		foreach ( $args['args'] as $name => $options ) {
			if ( ! is_array( $options ) || ! self::describes_single_value( $options ) ) {
				continue;
			}

			$args['args'][ $name ] = self::guard_scalar_arg( (string) $name, $options );
		}

		return $args;
	}

	private static function describes_single_value( array $options ) {
		if ( isset( $options['type'] ) ) {
			$types = array_filter( (array) $options['type'], 'is_string' );

			if ( array() === $types ) {
				return false;
			}

			foreach ( $types as $type ) {
				if ( ! in_array( $type, self::SCALAR_TYPES, true ) ) {
					return false;
				}
			}

			return true;
		}

		foreach ( array( 'validate_callback', 'sanitize_callback' ) as $key ) {
			if ( ! isset( $options[ $key ] ) || ! self::is_scalar_helper( $options[ $key ] ) ) {
				continue;
			}

			return true;
		}

		return false;
	}

	private static function is_scalar_helper( $callback ) {
		if ( ! is_array( $callback ) || ! isset( $callback[0], $callback[1] ) || ! is_string( $callback[0] ) ) {
			return false;
		}

		return in_array( $callback[0], self::SCALAR_CALLBACK_CLASSES, true )
			&& ! in_array( $callback[1], self::LIST_CALLBACKS, true );
	}

	private static function guard_scalar_arg( $name, array $options ) {
		$inner = isset( $options['validate_callback'] ) ? $options['validate_callback'] : null;

		$options['validate_callback'] = function ( $value, $request = null, $key = null ) use ( $name, $inner ) {
			if ( null !== $value && ! is_scalar( $value ) ) {
				return new WP_Error(
					'rest_invalid_param',
					/* translators: %s: the name of the request parameter that was refused. */
					sprintf( __( 'Invalid parameter(s): %s', 'sitepress' ), $name ),
					array(
						'status' => 400,
						'params' => array(
							/* translators: %s: the name of the request parameter that was refused. */
							$name => sprintf( __( '%s must be a single value, not a list.', 'sitepress' ), $name ),
						),
					)
				);
			}

			if ( $inner ) {
				return call_user_func( $inner, $value, $request, $key );
			}

			return true;
		};

		return $options;
	}

	private function ensure_permission( $route, array $args ) {
		$routeId = $this->namespace . $route;

		if ( ! array_key_exists( 'permission_callback', $args ) || ! $args['permission_callback'] ) {
			$args['permission_callback'] = \WPML\Request\Adapter\Rest::permission( $this->base_policy(), $routeId );

			return $args;
		}

		$args['permission_callback'] = \WPML\Request\Adapter\Rest::requirePolicy( $routeId, $args['permission_callback'] );

		return $args;
	}

	protected function base_policy() {
		return \WPML\Request\Policy\Policy::authorize(
			array( $this, 'validate_permission' ),
			\WPML\Request\Policy\Authenticity::restTransport(),
			get_class( $this ) . '::get_allowed_capabilities() (any-of) + wp_rest nonce, via validate_permission()'
		);
	}

	private function user_has_matching_capabilities( WP_REST_Request $request ) {
		$capabilities = $this->get_allowed_capabilities( $request );

		$user_can = false;
		if ( self::CAPABILITY_EXTERNAL === $capabilities ) {
			$user_can = true;
		} elseif ( is_string( $capabilities ) ) {
			$user_can = current_user_can( $capabilities );
		} elseif ( is_array( $capabilities ) ) {
			foreach ( $capabilities as $capability ) {
				$user_can = $user_can || current_user_can( $capability );
			}
		}

		return $user_can;
	}

	private function get_nonce( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'x_wp_nonce' );
		if ( ! $nonce ) {
			$nonce = $request->get_param( '_wpnonce' );
		}

		return $nonce;
	}

}
