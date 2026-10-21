<?php

namespace WPML\LanguageEditor;

class ActiveLanguages {

	public static function codes() {
		global $wpdb;

		return array_map( 'strval', (array) $wpdb->get_col( "SELECT code FROM {$wpdb->prefix}icl_languages WHERE active = 1" ) );
	}

	public static function allCodes() {
		global $wpdb;

		return array_map( 'strval', (array) $wpdb->get_col( "SELECT code FROM {$wpdb->prefix}icl_languages" ) );
	}

	public static function rowId( $code ) {
		global $wpdb;

		$id = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$wpdb->prefix}icl_languages WHERE code = %s", (string) $code )
		);

		return $id !== null ? (int) $id : null;
	}

	public static function displayCodes() {
		global $wpdb;
		if ( ! self::hasColumn( 'display_code' ) ) {
			return [];
		}

		return array_map(
			'strval',
			(array) $wpdb->get_col( "SELECT display_code FROM {$wpdb->prefix}icl_languages WHERE active = 1 AND display_code IS NOT NULL AND display_code <> ''" )
		);
	}

	public static function bcp47Tags() {
		global $wpdb;
		if ( ! self::hasColumn( 'bcp_47' ) ) {
			return [];
		}

		return array_map(
			'strval',
			(array) $wpdb->get_col( "SELECT bcp_47 FROM {$wpdb->prefix}icl_languages WHERE active = 1 AND bcp_47 IS NOT NULL AND bcp_47 <> ''" )
		);
	}

	public static function locales() {
		global $wpdb;

		return array_map(
			'strval',
			(array) $wpdb->get_col( "SELECT default_locale FROM {$wpdb->prefix}icl_languages WHERE active = 1 AND default_locale IS NOT NULL AND default_locale <> ''" )
		);
	}

	public static function isActive( $code ) {
		return in_array( (string) $code, self::codes(), true );
	}

	public static function preset( $presetCode ) {
		global $wpdb;

		$table = $wpdb->prefix . 'icl_language_presets';

		if ( ! \WPML\Upgrade\SchemaFacts::hasTable( 'icl_language_presets' ) ) {
			return null;
		}

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE code = %s", (string) $presetCode ) );
		if ( ! $row ) {
			return null;
		}

		$m2m       = $wpdb->prefix . 'icl_language_preset_countries';
		$m2mExists = \WPML\Upgrade\SchemaFacts::hasTable( 'icl_language_preset_countries' );
		$allowed   = [];
		$default   = null;
		if ( $m2mExists ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT country_code, is_default FROM {$m2m} WHERE preset_code = %s ORDER BY sort_order ASC", (string) $presetCode ) );
			foreach ( (array) $rows as $r ) {
				$cc        = strtoupper( (string) $r->country_code );
				$allowed[] = $cc;
				if ( 1 === (int) $r->is_default ) {
					$default = $cc;
				}
			}
		}
		$row->allowed_countries = wp_json_encode( $allowed );
		$row->default_country   = $default;
		if ( ! isset( $row->language ) ) {
			$row->language = '';
		}

		return $row;
	}

	public static function pairs() {
		global $wpdb;

		$rows = self::hasColumn( 'country' )
			? $wpdb->get_results( "SELECT code, country FROM {$wpdb->prefix}icl_languages WHERE active = 1" )
			: $wpdb->get_results( "SELECT code, NULL AS country FROM {$wpdb->prefix}icl_languages WHERE active = 1" );

		return self::toPairs( $rows );
	}

	public static function inactivePairs() {
		global $wpdb;

		$rows = self::hasColumn( 'country' )
			? $wpdb->get_results( "SELECT code, country FROM {$wpdb->prefix}icl_languages WHERE active <> 1" )
			: $wpdb->get_results( "SELECT code, NULL AS country FROM {$wpdb->prefix}icl_languages WHERE active <> 1" );

		return self::toPairs( $rows );
	}

	public static function countryByCode() {
		global $wpdb;

		if ( ! is_object( $wpdb ) ) {
			return [];
		}

		$country = self::hasColumn( 'country' ) ? 'country' : 'NULL AS country';
		$rows = $wpdb->get_results( "SELECT code, {$country} FROM {$wpdb->prefix}icl_languages WHERE active = 1" );

		$map = [];
		foreach ( (array) $rows as $r ) {
			$map[ (string) $r->code ] = $r->country !== null && $r->country !== '' ? (string) $r->country : null;
		}

		return $map;
	}

	public static function withLanguageField( array $rows ) {
		foreach ( $rows as $key => $row ) {
			if ( ! is_array( $row ) || ! isset( $row['code'] ) ) {
				continue;
			}

			$identity                 = LanguageCodeResolution::publishedIdentity( (string) $row['code'] );
			$rows[ $key ]['language'] = $identity['language'];
		}

		return $rows;
	}

	private static function toPairs( $rows ) {
		$out = [];
		foreach ( (array) $rows as $r ) {
			$code  = (string) $r->code;
			$out[] = [
				'code'    => $code,
				'country' => $r->country !== null && $r->country !== '' ? (string) $r->country : null,
				'head'    => LanguageCodeResolution::head( $code ),
			];
		}

		return $out;
	}

	private static function hasColumn( $column ) {
		return \WPML\Upgrade\SchemaFacts::hasColumn( 'icl_languages', $column );
	}
}
