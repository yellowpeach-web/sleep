<?php

namespace WPML\Installer;

class CliDeferredLoader {

	const DELEGATOR = 'wpml_installer_instance_delegator';

	const TRIGGER_HOOKS = [
		'plugins_api',
		'pre_set_site_transient_update_plugins',
		'site_transient_update_plugins',
		'themes_api',
		'site_transient_update_themes',
		'themes_update_check_locales',
		'pre_update_site_option_auto_update_plugins',
		'activate_plugin',
		'deactivated_plugin',
		'cron_schedules',
		'otgs_send_components_data',
		'otgs_send_components_data_on_product_registration',
		'otgs_installer_site_key_remove_retry',
		'otgs_installer_site_key_update',
	];

	const REPLAYED_HOOKS = [ 'init', 'wp_loaded' ];

	private static $deferred = false;

	private static $loading = false;

	private static $delegate;

	private static $classMap;

	public static function register() {
		add_action( 'after_setup_theme', [ self::class, 'defer' ], 0 );
	}

	public static function defer() {
		$priority = has_action( 'after_setup_theme', self::DELEGATOR );
		if ( self::$deferred || false === $priority || class_exists( 'WP_Installer', false ) || ! function_exists( self::DELEGATOR ) ) {
			return;
		}

		$delegate = self::chooseDelegate();
		if ( ! $delegate ) {
			return;
		}

		self::$deferred = true;
		self::$delegate = $delegate;
		remove_action( 'after_setup_theme', self::DELEGATOR, $priority );

		$functions = dirname( $delegate['bootfile'] ) . '/includes/functions-core.php';
		if ( is_readable( $functions ) ) {
			include_once $functions;
		}

		spl_autoload_register( [ self::class, 'autoload' ] );
		foreach ( self::TRIGGER_HOOKS as $hook ) {
			add_filter( $hook, [ self::class, 'loadOnHook' ], PHP_INT_MIN, 1 );
		}
	}

	public static function loadOnHook( $value = null ) {
		self::load();

		return $value;
	}

	public static function autoload( $className ) {
		if ( ! self::looksLikeInstallerClass( $className ) ) {
			return;
		}
		$file = self::classFile( $className );
		if ( null === $file ) {
			return;
		}
		include_once $file;
		self::load();
	}

	public static function load() {
		if ( ! self::$deferred || self::$loading ) {
			return;
		}
		self::$loading = true;


		$before = [];
		foreach ( self::REPLAYED_HOOKS as $hook ) {
			$before[ $hook ] = self::callbackIds( $hook );
		}

		call_user_func( self::DELEGATOR );

		foreach ( self::REPLAYED_HOOKS as $hook ) {
			if ( did_action( $hook ) ) {
				self::replay( $hook, $before[ $hook ] );
			}
		}

		spl_autoload_unregister( [ self::class, 'autoload' ] );
		self::$deferred = false;
		self::$loading  = false;
	}

	private static function chooseDelegate() {
		$instances = isset( $GLOBALS['wp_installer_instances'] ) && is_array( $GLOBALS['wp_installer_instances'] )
			? $GLOBALS['wp_installer_instances']
			: [];

		$delegate = null;
		foreach ( $instances as $instance ) {
			if ( null === $delegate || version_compare( (string) ( $instance['version'] ?? '' ), (string) ( $delegate['version'] ?? '' ), '>' ) ) {
				$delegate = $instance;
			}
		}

		$highestPriority = null;
		foreach ( $instances as $instance ) {
			if ( isset( $instance['args']['high_priority'] ) ) {
				if ( null === $highestPriority || $instance['args']['high_priority'] <= $highestPriority ) {
					$highestPriority = $instance['args']['high_priority'];
					$delegate        = $instance;
				}
			}
		}

		return is_array( $delegate ) && ! empty( $delegate['bootfile'] ) ? $delegate : null;
	}

	private static function looksLikeInstallerClass( $className ) {
		return 0 === strpos( $className, 'OTGS' )
			|| 0 === strpos( $className, 'WP_Installer' )
			|| 0 === strpos( $className, 'Installer_' )
			|| 0 === strpos( $className, 'Translation_Service_Info' )
			|| 0 === strpos( $className, 'WPML_Content_Stats' );
	}

	private static function classFile( $className ) {
		if ( null === self::$classMap ) {
			$file           = dirname( self::$delegate['bootfile'] ) . '/includes/otgs-installer-autoload-classmap.php';
			$map            = is_readable( $file ) ? require $file : [];
			self::$classMap = is_array( $map ) ? $map : [];
		}

		return isset( self::$classMap[ $className ] ) ? self::$classMap[ $className ] : null;
	}

	private static function callbackIds( $hook ) {
		$ids = [];
		foreach ( self::callbacks( $hook ) as $priority => $callbacks ) {
			foreach ( array_keys( $callbacks ) as $id ) {
				$ids[ $priority ][ $id ] = true;
			}
		}

		return $ids;
	}

	private static function callbacks( $hook ) {
		global $wp_filter;

		return isset( $wp_filter[ $hook ] ) && is_array( $wp_filter[ $hook ]->callbacks ) ? $wp_filter[ $hook ]->callbacks : [];
	}

	private static function replay( $hook, array $before ) {
		do {
			$added = [];
			foreach ( self::callbacks( $hook ) as $priority => $callbacks ) {
				foreach ( $callbacks as $id => $callback ) {
					if ( ! isset( $before[ $priority ][ $id ] ) ) {
						$added[] = [ (int) $priority, $callback ];
					}
				}
			}
			usort(
				$added,
				function ( $a, $b ) {
					return $a[0] <=> $b[0];
				}
			);
			foreach ( $added as $entry ) {
				remove_filter( $hook, $entry[1]['function'], $entry[0] );
			}
			foreach ( $added as $entry ) {
				call_user_func_array( $entry[1]['function'], array_slice( [ '' ], 0, (int) $entry[1]['accepted_args'] ) );
			}
		} while ( $added );
	}
}
