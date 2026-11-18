<?php

namespace WPML\Plugins;

class DependenciesSnapshotWrites {

	const NEEDS_VALIDATION  = 'wpml_dependencies:needs_validation';
	const INSTALLED_PLUGINS = 'wpml_dependencies:installed_plugins';
	const REQUIRED_BY       = 'wpml_dependencies:required_by';

	const NOT_REPLACED_LOGGED_OPTION = 'wpml_dependencies_snapshot_writes_not_replaced';

	private static $dependencies;

	public static $pluginDir = null;

	public static function register( $dependencies, $log = null ) {
		add_filter( 'pre_update_option_' . self::NEEDS_VALIDATION, [ self::class, 'keepUnchangedFlag' ], 10, 2 );

		if ( ! is_object( $dependencies ) || ! is_callable( [ $dependencies, 'init_plugins_action' ] ) ) {
			return false;
		}

		$handler  = [ $dependencies, 'run_validation_on_plugins_page' ];
		$priority = has_action( 'load-plugins.php', $handler );
		if ( false === $priority || ! remove_action( 'load-plugins.php', $handler, (int) $priority ) ) {
			self::logNotReplaced( $dependencies, $log );

			return false;
		}

		self::$dependencies = $dependencies;
		add_action( 'load-plugins.php', [ self::class, 'onPluginsScreen' ], (int) $priority );

		return true;
	}

	private static function logNotReplaced( $dependencies, $log ) {
		$file = (string) ( new \ReflectionClass( $dependencies ) )->getFileName();
		if ( get_option( self::NOT_REPLACED_LOGGED_OPTION ) === $file ) {
			return;
		}
		update_option( self::NOT_REPLACED_LOGGED_OPTION, $file, false );
		$message = sprintf(
			'WPML: the copy of wpml-lib-dependencies loaded first (%s) has no run_validation_on_plugins_page() hooked on load-plugins.php, so core did not replace it; the Plugins screen keeps that copy\'s own dependency validation (wpmldev-11939).',
			$file
		);
		if ( null === $log ) {
			error_log( $message );
		} else {
			call_user_func( $log, $message );
		}
	}

	public static function onPluginsScreen() {
		if ( self::$dependencies ) {
			self::validateIfVersionsMoved( self::$dependencies );
		}
	}

	public static function keepUnchangedFlag( $value, $oldValue ) {
		return (bool) $value === (bool) $oldValue ? $oldValue : $value;
	}

	public static function validateIfVersionsMoved( $dependencies ) {
		$active = self::activePlugins();
		if (
			! self::versionsMoved( get_option( self::INSTALLED_PLUGINS, false ), $active['versions'] )
			&& ! self::floorsMoved( get_option( self::REQUIRED_BY, false ), $active['floors'] )
		) {
			return false;
		}

		update_option( self::NEEDS_VALIDATION, true );
		$dependencies->init_plugins_action();

		return true;
	}

	public static function versionsMoved( $recorded, array $active ) {
		if ( ! is_array( $recorded ) ) {
			return true;
		}
		foreach ( $recorded as $slug => $version ) {
			if ( ! array_key_exists( $slug, $active ) || (string) $active[ $slug ] !== (string) $version ) {
				return true;
			}
		}

		return false;
	}

	public static function floorsMoved( $recorded, array $declared ) {
		if ( ! is_array( $recorded ) ) {
			return true;
		}
		$was = [];
		foreach ( $recorded as $component => $requirers ) {
			foreach ( (array) $requirers as $requirer => $requirement ) {
				$was[ $component ][ $requirer ] = isset( $requirement['version'] ) ? (string) $requirement['version'] : '';
			}
		}

		return self::sorted( $was ) !== self::sorted( $declared );
	}

	private static function sorted( array $map ) {
		ksort( $map );
		foreach ( $map as $key => $inner ) {
			ksort( $inner );
			$map[ $key ] = $inner;
		}

		return $map;
	}

	private static function activePlugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$siteActive = [];
		foreach ( wp_get_active_and_valid_plugins() as $path ) {
			$siteActive[ plugin_basename( $path ) ] = true;
		}

		$versions = [];
		$floors   = [];
		foreach ( get_plugins() as $file => $data ) {
			$folder = dirname( $file );
			if ( '.' === $folder || ! isset( $siteActive[ $file ] ) ) {
				continue;
			}
			$slug              = ! empty( $data['Plugin Slug'] ) ? $data['Plugin Slug'] : $folder;
			$versions[ $slug ] = isset( $data['Version'] ) ? (string) $data['Version'] : '';

			$json = ( null === self::$pluginDir ? WP_PLUGIN_DIR : self::$pluginDir ) . '/' . $folder . '/wpml-dependencies.json';
			if ( is_readable( $json ) ) {
				$bundle = json_decode( (string) file_get_contents( $json ), true );
				foreach ( is_array( $bundle ) ? $bundle : [] as $component => $floor ) {
					$floors[ $component ][ $slug ] = (string) $floor;
				}
			}
		}

		return [
			'versions' => $versions,
			'floors'   => $floors,
		];
	}
}
