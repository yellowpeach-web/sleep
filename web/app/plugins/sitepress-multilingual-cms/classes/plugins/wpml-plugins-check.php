<?php

class WPML_Plugins_Check {

	const OUTDATED_ST_NAMESPACES = [ 'WPML\\StringTranslation\\', 'WPML\\ST\\' ];

	private static $st_outdated = false;

	public static function disable_outdated(
		$bundle_json,
		$tm_version,
		$st_version,
		$wcml_version,
		$acfml_version = null,
		$st_installed = null
	) {
		$required_versions = json_decode( $bundle_json, true );

		self::$st_outdated = false;

		if ( self::is_below_floor( $st_version, $required_versions['wpml-string-translation'] ) ) {
			self::$st_outdated = null === $st_installed ? defined( 'WPML_ST_VERSION' ) : (bool) $st_installed;

			self::remove_outdated_st_hooks();

			self::remove_outdated_st_boot();

			add_action( 'plugins_loaded', [ __CLASS__, 'sweep_outdated_st_hooks' ], PHP_INT_MAX );

			add_action( 'plugins_loaded', [ __CLASS__, 'remove_outdated_st_hooks' ], PHP_INT_MAX );

			self::install_outdated_st_stand_in();
			add_action( 'plugins_loaded', [ __CLASS__, 'install_outdated_st_stand_in' ], PHP_INT_MAX );
		}

		if ( self::is_below_floor( $wcml_version, $required_versions['woocommerce-multilingual'] ) ) {
			global $woocommerce_wpml;

			if ( $woocommerce_wpml ) {
				remove_action( 'wpml_loaded', [ $woocommerce_wpml, 'load' ] );
				remove_action( 'init', [ $woocommerce_wpml, 'init' ], 2 );
			}

			remove_action( 'wpml_loaded', 'wcml_loader' );

			self::install_outdated_wcml_stand_in();
			add_action( 'plugins_loaded', [ __CLASS__, 'install_outdated_wcml_stand_in' ], PHP_INT_MAX );
		}

		if ( null !== $acfml_version
			&& isset( $required_versions['acfml'] )
			&& self::is_below_floor( $acfml_version, $required_versions['acfml'] )
		) {
			remove_action( 'wpml_loaded', 'acfmlInit' );
		}
	}

	public static function is_below_floor( $version, $floor ) {
		$release = (string) preg_replace( '/-.*$/', '', (string) $version );

		return version_compare( $release, (string) $floor, '<' );
	}

	public static function install_outdated_st_stand_in() {
		if ( ! isset( $GLOBALS['WPML_String_Translation'] ) || ! is_object( $GLOBALS['WPML_String_Translation'] ) ) {
			$GLOBALS['WPML_String_Translation'] = new WPML_ST_Outdated_Stand_In();
		}

		if ( function_exists( 'WPML\\Container\\alias' ) ) {
			\WPML\Container\alias( [ 'WPML\\ST\\TranslateWpmlString' => WPML_ST_Outdated_Translate_Stand_In::class ] );
		}

		$dependencies = self::dependencies();
		if ( method_exists( $dependencies, 'add_paragraph' ) ) {
			$dependencies->add_paragraph(
				esc_html__( 'Until you update String Translation, it stays switched off: translated strings show in their original language and new strings are not registered. Updating it brings everything back.', 'sitepress' ),
				2
			);
		}
	}

	public static function dependencies() {
		return WPML_Dependencies::get_instance();
	}

	public static function install_outdated_wcml_stand_in() {
		global $woocommerce_wpml;

		if ( ! is_object( $woocommerce_wpml ) ) {
			return;
		}

		$reflection = new ReflectionObject( $woocommerce_wpml );

		foreach ( $reflection->getProperties( ReflectionProperty::IS_PUBLIC ) as $property ) {
			if ( $property->isStatic() || ( $property->isInitialized( $woocommerce_wpml ) && null !== $property->getValue( $woocommerce_wpml ) ) ) {
				continue;
			}

			$standIn = WPML_Outdated_Companion_Stand_In::forType( $property->getType() );

			if ( $standIn ) {
				$property->setValue( $woocommerce_wpml, $standIn );
			}
		}
	}

	public static function remove_outdated_st_hooks() {
		remove_action( 'wpml_before_init', 'load_wpml_st_basics' );
		remove_action( 'plugins_loaded', 'icl_st_init' );
		remove_action( 'wpml_register_single_string', 'wpml_register_single_string_action' );
		remove_filter( 'register_string_for_translation', 'icl_register_string' );
		remove_filter( 'translate_string', 'translate_string_filter' );
		remove_filter( 'wpml_translate_single_string', 'wpml_translate_single_string_filter' );
		remove_action( 'wpml_parse_config_file', 'wpml_st_parse_config' );
		remove_action( 'wpml_parse_custom_config', 'wpml_st_parse_config' );
		remove_filter( 'wpml_tm_allowed_source_languages', 'filter_tm_source_langs' );
		remove_filter( 'wpml_job_assigned_to_after_assignment', 'wpml_st_filter_job_assignment' );
	}

	public static function isStOutdated() {
		return self::$st_outdated;
	}

	public static function remove_outdated_st_boot() {
		foreach ( self::registered_callbacks( 'plugins_loaded' ) as $priority => $callbacks ) {
			foreach ( self::callback_list( $callbacks ) as $registered ) {
				$callback = $registered['function'];

				if ( is_array( $callback )
					&& isset( $callback[0], $callback[1] )
					&& $callback[0] instanceof WPML_ST_Initialize
					&& 'run' === $callback[1]
				) {
					remove_action( 'plugins_loaded', $callback, (int) $priority );
				}
			}
		}
	}

	public static function sweep_outdated_st_hooks() {
		if ( ! self::isStOutdated() ) {
			return;
		}

		if ( empty( $GLOBALS['wp_filter'] ) || ! is_array( $GLOBALS['wp_filter'] ) ) {
			return;
		}

		foreach ( array_keys( $GLOBALS['wp_filter'] ) as $hook_name ) {
			foreach ( self::registered_callbacks( $hook_name ) as $priority => $callbacks ) {
				foreach ( self::callback_list( $callbacks ) as $registered ) {
					if ( self::is_outdated_st_callback( $registered['function'] ) ) {
						remove_filter( (string) $hook_name, $registered['function'], (int) $priority );
					}
				}
			}
		}
	}

	private static function registered_callbacks( $hook_name ) {
		if ( ! isset( $GLOBALS['wp_filter'][ $hook_name ] ) ) {
			return [];
		}

		$hook = $GLOBALS['wp_filter'][ $hook_name ];

		if ( is_object( $hook ) && isset( $hook->callbacks ) && is_array( $hook->callbacks ) ) {
			return $hook->callbacks;
		}

		return is_array( $hook ) ? $hook : [];
	}

	private static function callback_list( $callbacks ) {
		if ( ! is_array( $callbacks ) ) {
			return [];
		}

		return array_filter(
			$callbacks,
			function ( $registered ) {
				return is_array( $registered ) && isset( $registered['function'] );
			}
		);
	}

	private static function is_outdated_st_callback( $callback ) {
		try {
			if ( $callback instanceof Closure ) {
				$bound = ( new ReflectionFunction( $callback ) )->getClosureThis();

				return null !== $bound && self::is_outdated_st_class( get_class( $bound ) );
			}

			if ( is_array( $callback ) && isset( $callback[0] ) ) {
				return self::is_outdated_st_class(
					is_object( $callback[0] ) ? get_class( $callback[0] ) : $callback[0]
				);
			}

			if ( is_string( $callback ) && false !== strpos( $callback, '::' ) ) {
				return self::is_outdated_st_class( strstr( $callback, '::', true ) );
			}

			if ( is_object( $callback ) ) {
				return self::is_outdated_st_class( get_class( $callback ) );
			}
		} catch ( Throwable $throwable ) {
			return false;
		}

		return false;
	}

	private static function is_outdated_st_class( $class_name ) {
		if ( ! is_string( $class_name ) ) {
			return false;
		}

		$class_name = ltrim( $class_name, '\\' );

		foreach ( self::OUTDATED_ST_NAMESPACES as $namespace ) {
			if ( 0 === strpos( $class_name, $namespace ) ) {
				return true;
			}
		}

		return false;
	}
}
