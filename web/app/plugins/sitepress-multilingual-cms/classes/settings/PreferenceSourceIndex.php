<?php

namespace WPML\TM\Settings;

require_once __DIR__ . '/../../inc/constants-since-5-0.php';

use WPML\Core\Component\CustomFieldPreferences\Domain\ElementType;

class PreferenceSourceIndex {

	const KIND_THEME = 'theme-config';

	const KIND_PLUGIN = 'plugin-config';

	const KIND_REMOTE = 'remote-config';

	const KIND_CUSTOM_XML = 'custom-xml';

	private static $captured = array();

	private static function types() {
		return array(
			ElementType::POST => array(
				'readonly' => WPML_POST_META_READONLY_SETTING_INDEX,
				'source'   => WPML_POST_META_SOURCE_SETTING_INDEX,
				'plural'   => WPML_POST_META_CONFIG_INDEX_PLURAL,
				'singular' => WPML_POST_META_CONFIG_INDEX_SINGULAR,
			),
			ElementType::TERM => array(
				'readonly' => WPML_TERM_META_READONLY_SETTING_INDEX,
				'source'   => WPML_TERM_META_SOURCE_SETTING_INDEX,
				'plural'   => WPML_TERM_META_CONFIG_INDEX_PLURAL,
				'singular' => WPML_TERM_META_CONFIG_INDEX_SINGULAR,
			),
		);
	}

	public static function reset() {
		self::$captured = array();
	}

	public static function captureFile( $file, $config, array $roots = array() ) {
		self::capture( self::describe( $file, $roots ), $config );
	}

	public static function captureCustomXml( $config ) {
		self::capture(
			array(
				'kind' => self::KIND_CUSTOM_XML,
				'file' => null,
			),
			$config
		);
	}

	private static function capture( $descriptor, $config ) {
		if ( null === $descriptor || ! is_array( $config ) || ! isset( $config['wpml-config'] ) || ! is_array( $config['wpml-config'] ) ) {
			return;
		}

		foreach ( self::types() as $type => $keys ) {
			foreach ( self::namesIn( $config['wpml-config'], $keys['plural'], $keys['singular'] ) as $name ) {
				self::$captured[ $type ][ $name ] = $descriptor;
			}
		}
	}

	public static function namesIn( array $config, $plural, $singular ) {
		if ( empty( $config[ $plural ] ) || ! is_array( $config[ $plural ] ) || ! isset( $config[ $plural ][ $singular ] ) ) {
			return array();
		}

		$declared = $config[ $plural ][ $singular ];
		if ( ! is_array( $declared ) ) {
			return array();
		}

		$entries = isset( $declared['value'] ) ? array( $declared ) : $declared;

		$names = array();
		foreach ( $entries as $entry ) {
			if ( is_array( $entry ) && isset( $entry['value'] ) && is_scalar( $entry['value'] ) ) {
				$name = trim( (string) $entry['value'] );
				if ( '' !== $name ) {
					$names[] = $name;
				}
			}
		}

		return $names;
	}

	public static function describe( $file, array $roots = array() ) {
		if ( is_object( $file ) ) {
			return array(
				'kind' => self::KIND_REMOTE,
				'file' => null,
			);
		}

		$path = self::normalize( (string) $file );
		if ( '' === $path ) {
			return null;
		}

		foreach ( ( $roots ?: self::roots() ) as $prefix => $root ) {
			$relative = self::relativeTo( $path, (string) $root, (string) $prefix );
			if ( null !== $relative ) {
				return array(
					'kind' => 'themes' === $prefix ? self::KIND_THEME : self::KIND_PLUGIN,
					'file' => $relative,
				);
			}
		}

		return null;
	}

	private static function roots() {
		$roots = array();

		if ( function_exists( 'get_theme_root' ) ) {
			$roots['themes'] = (string) get_theme_root();
		}
		if ( defined( 'WPMU_PLUGIN_DIR' ) ) {
			$roots['mu-plugins'] = (string) WPMU_PLUGIN_DIR;
		}
		if ( defined( 'WP_PLUGIN_DIR' ) ) {
			$roots['plugins'] = (string) WP_PLUGIN_DIR;
		}

		return $roots;
	}

	private static function relativeTo( $path, $root, $prefix ) {
		$root = rtrim( self::normalize( $root ), '/' );
		if ( '' === $root ) {
			return null;
		}
		$root .= '/';

		return 0 === strpos( $path, $root )
			? $prefix . '/' . substr( $path, strlen( $root ) )
			: null;
	}

	private static function normalize( $path ) {
		return function_exists( 'wp_normalize_path' )
			? (string) wp_normalize_path( $path )
			: str_replace( '\\', '/', $path );
	}

	public static function persist( $tm ) {
		if ( ! is_object( $tm ) ) {
			return false;
		}

		$settings = $tm->settings;
		if ( ! is_array( $settings ) ) {
			return false;
		}

		$changed = false;

		foreach ( self::types() as $type => $keys ) {
			$readonly = isset( $settings[ $keys['readonly'] ] ) && is_array( $settings[ $keys['readonly'] ] )
				? $settings[ $keys['readonly'] ]
				: array();

			$index = array();
			foreach ( $readonly as $name ) {
				$name = (string) $name;
				if ( isset( self::$captured[ $type ][ $name ] ) ) {
					$index[ $name ] = self::$captured[ $type ][ $name ];
				}
			}

			$stored = isset( $settings[ $keys['source'] ] ) ? $settings[ $keys['source'] ] : null;

			if ( $index ) {
				if ( $stored !== $index ) {
					$tm->settings[ $keys['source'] ] = $index;
					$changed                         = true;
				}
			} elseif ( null !== $stored && array() !== $stored ) {
				$tm->settings[ $keys['source'] ] = array();
				$changed                         = true;
			}
		}

		if ( $changed ) {
			$tm->save_settings();
		}

		return $changed;
	}
}
