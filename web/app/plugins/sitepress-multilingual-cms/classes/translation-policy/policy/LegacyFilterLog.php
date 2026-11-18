<?php

namespace WPML\TranslationPolicy\Policy;

class LegacyFilterLog {

	const OPTION = 'wpml_translation_policy_legacy_editor_filters';

	private $checked = [];

	private $roots;

	public function __construct( ?array $roots = null ) {
		$this->roots = $roots;
	}

	public function record( string $hook ) {
		if ( isset( $this->checked[ $hook ] ) ) {
			return;
		}
		$this->checked[ $hook ] = true;

		$plugins = $this->pluginsOn( $hook );
		if ( ! $plugins ) {
			return;
		}

		$stored = get_option( self::OPTION, [] );
		$stored = is_array( $stored ) ? $stored : [];
		$known  = isset( $stored[ $hook ] ) && is_array( $stored[ $hook ] ) ? $stored[ $hook ] : [];
		$new    = array_diff( $plugins, array_keys( $known ) );
		if ( ! $new ) {
			return;
		}

		foreach ( $new as $plugin ) {
			$known[ $plugin ] = defined( 'ICL_SITEPRESS_VERSION' ) ? (string) ICL_SITEPRESS_VERSION : '';
			error_log( sprintf( 'WPML: "%s" uses the filter %s, which WPML stops applying in a coming release. Use the translation policy constraints instead.', $plugin, $hook ) );
		}
		$stored[ $hook ] = $known;
		update_option( self::OPTION, $stored, false );
	}

	public function pluginsOn( string $hook ): array {
		global $wp_filter;

		if ( ! isset( $wp_filter[ $hook ]->callbacks ) || ! is_array( $wp_filter[ $hook ]->callbacks ) ) {
			return [];
		}

		$plugins = [];
		foreach ( $wp_filter[ $hook ]->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$plugin = $this->ownerOf( self::fileOf( $callback['function'] ?? null ) );
				if ( '' !== $plugin ) {
					$plugins[ $plugin ] = true;
				}
			}
		}

		return array_keys( $plugins );
	}

	private static function fileOf( $callback ): string {
		try {
			if ( is_string( $callback ) && false !== strpos( $callback, '::' ) ) {
				$callback = explode( '::', $callback, 2 );
			}
			if ( is_array( $callback ) && 2 === count( $callback ) ) {
				$reflection = new \ReflectionMethod( $callback[0], $callback[1] );
			} elseif ( $callback instanceof \Closure || is_string( $callback ) ) {
				$reflection = new \ReflectionFunction( $callback );
			} elseif ( is_object( $callback ) && method_exists( $callback, '__invoke' ) ) {
				$reflection = new \ReflectionMethod( $callback, '__invoke' );
			} else {
				return '';
			}
		} catch ( \ReflectionException $e ) {
			return '';
		}

		return (string) $reflection->getFileName();
	}

	public function ownerOf( string $file ): string {
		if ( '' === $file ) {
			return '';
		}
		$file = wp_normalize_path( $file );

		if ( null === $this->roots ) {
			$relative = plugin_basename( $file );
			if ( $relative !== $file && '/' !== $relative[0] ) {
				$mu = defined( 'WPMU_PLUGIN_DIR' ) && 0 === strpos( $file, trailingslashit( wp_normalize_path( WPMU_PLUGIN_DIR ) ) );

				return ( $mu ? 'mu-plugins/' : '' ) . explode( '/', $relative )[0];
			}
		}

		$roots = $this->roots ?? [ 'theme/' => get_theme_root() ];

		foreach ( $roots as $prefix => $root ) {
			$root = '' === $root ? '' : trailingslashit( wp_normalize_path( $root ) );
			if ( '' !== $root && 0 === strpos( $file, $root ) ) {
				$parts = explode( '/', substr( $file, strlen( $root ) ) );

				return $prefix . $parts[0];
			}
		}

		return '';
	}
}
