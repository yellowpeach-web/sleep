<?php

namespace WPML\Upgrade;

class SchemaFacts {

	const OPTION = 'wpml_schema_facts';

	private static $acrossRequests = false;

	private static $facts = [];

	private static $stamps = [];

	private static $dirty = [];

	private static $pending = [];

	private static $shutdownHooked = false;

	public static function keepAcrossRequests( $keep = true ) {
		self::$acrossRequests = (bool) $keep;
		self::forget();
		self::$pending = [];
	}

	public static function hasTable( $table ) {
		global $wpdb;

		if ( ! is_object( $wpdb ) ) {
			return false;
		}

		return self::fact(
			$wpdb,
			$table,
			function () use ( $wpdb, $table ) {
				return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . $table ) );
			}
		);
	}

	public static function hasColumn( $table, $column ) {
		global $wpdb;

		if ( ! is_object( $wpdb ) ) {
			return false;
		}

		return self::fact(
			$wpdb,
			$table . '.' . $column,
			function () use ( $wpdb, $table, $column ) {
				switch ( $table ) {
					case 'icl_languages':
						return (bool) $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `{$wpdb->prefix}icl_languages` LIKE %s", $column ) );
					case 'icl_language_presets':
						return (bool) $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `{$wpdb->prefix}icl_language_presets` LIKE %s", $column ) );
					case 'icl_language_preset_countries':
						return (bool) $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `{$wpdb->prefix}icl_language_preset_countries` LIKE %s", $column ) );
					case 'icl_string_packages':
						return (bool) $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `{$wpdb->prefix}icl_string_packages` LIKE %s", $column ) );
				}

				throw new \InvalidArgumentException( 'SchemaFacts has no column probe for this table: add its literal spelling to hasColumn().' );
			}
		);
	}

	public static function forget() {
		foreach ( array_keys( self::$dirty ) as $prefix ) {
			if ( isset( self::$stamps[ $prefix ], self::$facts[ $prefix ] ) ) {
				self::keepPending( (string) $prefix, self::$stamps[ $prefix ], array_filter( self::$facts[ $prefix ] ) );
			}
		}

		self::$facts  = [];
		self::$stamps = [];
		self::$dirty  = [];
	}

	public static function forgetIfQueryFailed( $wpdb ) {
		if ( ! is_object( $wpdb ) || ! isset( $wpdb->last_error ) || '' === (string) $wpdb->last_error ) {
			return;
		}

		self::forgetKept();
	}

	public static function forgetKept() {
		self::forget();
		self::$pending = [];
		if ( self::$acrossRequests && false !== get_option( self::OPTION, false ) ) {
			delete_option( self::OPTION );
		}
	}

	public static function persist() {
		global $wpdb;

		self::$shutdownHooked = false;
		if ( ! self::$acrossRequests || ! is_object( $wpdb ) ) {
			return;
		}

		$prefix  = (string) $wpdb->prefix;
		$dirty   = ! empty( self::$dirty[ $prefix ] ) && isset( self::$stamps[ $prefix ] );
		$pending = isset( self::$pending[ $prefix ] ) ? self::$pending[ $prefix ] : null;
		unset( self::$dirty[ $prefix ], self::$pending[ $prefix ] );
		if ( ! $dirty && null === $pending ) {
			return;
		}

		$stamp = self::stamp();
		$kept  = self::keptFacts( $prefix, $stamp );
		$facts = $kept;
		if ( $dirty && self::$stamps[ $prefix ] === $stamp ) {
			$facts = array_merge( $facts, array_filter( self::$facts[ $prefix ] ) );
		}
		if ( null !== $pending && $pending['stamp'] === $stamp ) {
			$facts = array_merge( $facts, $pending['facts'] );
		}

		if ( $facts === $kept ) {
			return;
		}

		update_option(
			self::OPTION,
			[
				'prefix' => $prefix,
				'stamp'  => $stamp,
				'facts'  => $facts,
			],
			true
		);
	}

	private static function keepPending( $prefix, $stamp, array $facts ) {
		if ( ! $facts ) {
			return;
		}

		if ( isset( self::$pending[ $prefix ] ) && self::$pending[ $prefix ]['stamp'] === $stamp ) {
			$facts = array_merge( self::$pending[ $prefix ]['facts'], $facts );
		}

		self::$pending[ $prefix ] = [
			'stamp' => $stamp,
			'facts' => $facts,
		];
	}

	private static function keptFacts( $prefix, $stamp ) {
		$kept = get_option( self::OPTION, false );
		if (
			is_array( $kept )
			&& isset( $kept['prefix'], $kept['stamp'], $kept['facts'] )
			&& $kept['prefix'] === $prefix
			&& $stamp === $kept['stamp']
			&& is_array( $kept['facts'] )
		) {
			return array_filter( array_map( 'boolval', $kept['facts'] ) );
		}

		return [];
	}

	private static function fact( $wpdb, $key, callable $probe ) {
		$prefix = (string) $wpdb->prefix;

		if ( ! isset( self::$facts[ $prefix ] ) ) {
			self::load( $prefix );
		}

		if ( array_key_exists( $key, self::$facts[ $prefix ] ) ) {
			return self::$facts[ $prefix ][ $key ];
		}

		$exists                         = $probe();
		self::$facts[ $prefix ][ $key ] = $exists;

		if ( $exists && self::$acrossRequests ) {
			self::$dirty[ $prefix ] = true;
			if ( ! self::$shutdownHooked ) {
				self::$shutdownHooked = true;
				add_action( 'shutdown', [ __CLASS__, 'persist' ], PHP_INT_MAX );
			}
		}

		return $exists;
	}

	private static function load( $prefix ) {
		self::$facts[ $prefix ] = [];
		if ( ! self::$acrossRequests ) {
			return;
		}

		$stamp                   = self::stamp();
		self::$stamps[ $prefix ] = $stamp;

		self::$facts[ $prefix ] = self::keptFacts( $prefix, $stamp );
	}

	private static function stamp() {
		$version = defined( 'ICL_SITEPRESS_VERSION' ) ? (string) constant( 'ICL_SITEPRESS_VERSION' ) : '';

		return $version . ':' . md5( (string) wp_json_encode( get_option( CommandsStatus::OPTION_KEY, [] ) ) );
	}
}
