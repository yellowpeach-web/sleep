<?php

namespace WPML\LanguageEditor\Presets;

use WPML\LanguageEditor\Flags\FlagFile;
use WPML_TM_ATE_API;

class CatalogueFeedSource {

	const ENGLISH_NAME_CAP = 128;

	const WP_CODE_CAP = 35;

	private $api;

	public function __construct( WPML_TM_ATE_API $api ) {
		$this->api = $api;
	}

	public static function create() {
		return new self( \WPML\Container\make( WPML_TM_ATE_API::class ) );
	}

	public function descriptor() {
		return $this->api->get_ams_catalogue_version();
	}

	public function languages() {
		$body = $this->api->get_ams_catalogue_languages();
		if ( ! is_array( $body ) ) {
			return null;
		}

		$rawEntries = isset( $body['entries'] ) && is_array( $body['entries'] ) ? $body['entries'] : [];

		$entries      = [];
		$unrecognized = [];
		foreach ( $rawEntries as $index => $raw ) {
			$entry = self::normalizeEntry( $raw );
			if ( null === $entry ) {
				$unrecognized[] = self::identify( $raw, $index );
				continue;
			}
			$entries[] = $entry;
		}

		$version  = self::positiveInt( isset( $body['version'] ) ? $body['version'] : null );
		$declared = isset( $body['count'] ) && is_numeric( $body['count'] ) ? (int) $body['count'] : null;
		$checked  = count( $entries );

		$envelopeOk = isset( $body['schema'] ) && 1 === self::positiveInt( $body['schema'] )
			&& null !== $version
			&& isset( $body['entries'] ) && is_array( $body['entries'] )
			&& null !== $declared && $declared === count( $rawEntries );

		$full = $envelopeOk
			&& $checked === $declared;

		return new CatalogueFeed(
			CatalogueFeed::SOURCE_AMS,
			$full ? CatalogueFeed::AUTHORITY_FULL : CatalogueFeed::AUTHORITY_DEGRADED,
			$version,
			$declared,
			$checked,
			$entries,
			$unrecognized
		);
	}

	public function ateFallback() {
		$feed = $this->api->get_available_languages();

		return is_array( $feed ) ? $feed : [];
	}


	private static function normalizeEntry( $raw ) {
		if ( ! is_array( $raw ) && ! is_object( $raw ) ) {
			return null;
		}

		$code = strtolower( trim( (string) self::prop( $raw, 'code' ) ) );
		if ( ! CatalogueSyncRunner::isLanguageCode( $code ) ) {
			return null;
		}

		$englishName = self::stringOrNull( self::prop( $raw, 'english_name' ) );
		$language    = self::stringOrNull( self::prop( $raw, 'language' ) );
		if ( null === $englishName || null === $language ) {
			return null;
		}

		$rawCountries = self::prop( $raw, 'countries' );
		if ( ! is_array( $rawCountries ) ) {
			return null;
		}

		$countries   = [];
		$haveDefault = false;
		foreach ( $rawCountries as $rawCountry ) {
			if ( ! is_array( $rawCountry ) && ! is_object( $rawCountry ) ) {
				return null;
			}
			$countryCode = strtoupper( trim( (string) self::prop( $rawCountry, 'code' ) ) );
			if ( '' !== $countryCode && ! CatalogueSyncRunner::isCountryCode( $countryCode ) ) {
				return null;
			}
			if ( isset( $countries[ $countryCode ] ) ) {
				continue;
			}

			$isDefault = self::boolOrNull( self::prop( $rawCountry, 'is_default' ) );
			if ( true === $isDefault ) {
				if ( $haveDefault ) {
					$isDefault = false;
				}
				$haveDefault = true;
			}

			$countries[ $countryCode ] = [
				'country_code'       => $countryCode,
				'pair_code'          => self::pairCode( self::prop( $rawCountry, 'pair_code' ) ),
				'is_default'         => $isDefault,
				'picker_default'     => self::boolOrNull( self::prop( $rawCountry, 'picker_default' ) ),
				'sort_order'         => self::intOrNull( self::prop( $rawCountry, 'sort_order' ) ),
				'wp_locale'          => self::stringOrNull( self::prop( $rawCountry, 'wp_locale' ) ),
				'wp_locale_coverage' => self::intOrNull( self::prop( $rawCountry, 'wp_locale_coverage' ) ),
				'bcp_47'             => self::stringOrNull( self::prop( $rawCountry, 'bcp_47' ) ),
				'ate_supported'      => self::boolOrNull( self::prop( $rawCountry, 'ate_supported' ) ),
				'flag'               => self::flagName( self::prop( $rawCountry, 'flag' ), self::where( $code, $countryCode ) ),
				'offerable_flags'    => self::flagNames( self::prop( $rawCountry, 'offerable_flags' ), self::where( $code, $countryCode ) ),
			];
		}

		$wpCode = self::stringOrNull( self::prop( $raw, 'wp_code' ) );

		return [
			'code'            => $code,
			'english_name'    => self::clamp( $englishName, self::ENGLISH_NAME_CAP ),
			'language'        => $language,
			'script'          => self::stringOrNull( self::prop( $raw, 'script' ) ),
			'type'            => self::stringOrNull( self::prop( $raw, 'type' ) ),
			'country_mode'    => self::stringOrNull( self::prop( $raw, 'country_mode' ) ),
			'visibility_tier' => self::stringOrNull( self::prop( $raw, 'visibility_tier' ) ),
			'major'           => self::flagInt( self::prop( $raw, 'major' ) ),
			'rtl'             => self::flagInt( self::prop( $raw, 'rtl' ) ),
			'language_flag'   => self::flagName( self::prop( $raw, 'language_flag' ), $code ),
			'wp_code'         => ( null !== $wpCode && strlen( $wpCode ) > self::WP_CODE_CAP ) ? null : $wpCode,
			'countries'       => array_values( $countries ),
		];
	}

	private static function pairCode( $value ) {
		$value = self::stringOrNull( $value );
		if ( null === $value ) {
			return null;
		}

		$code = strtolower( trim( $value ) );

		return CatalogueSyncRunner::isLanguageCode( $code ) ? $code : null;
	}

	private static function clamp( $value, $cap ) {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $cap );
		}

		return substr( $value, 0, $cap );
	}

	private static function identify( $raw, $index ) {
		$code = ( is_array( $raw ) || is_object( $raw ) ) ? trim( (string) self::prop( $raw, 'code' ) ) : '';

		return '' === $code ? '#' . (string) $index : $code;
	}

	private static function where( $code, $countryCode ) {
		return '' === $countryCode ? $code : $code . '/' . $countryCode;
	}

	private static function positiveInt( $value ) {
		if ( ! is_numeric( $value ) ) {
			return null;
		}
		$int = (int) $value;

		return ( $int >= 1 && (string) $int === (string) $value ) ? $int : null;
	}

	private static function stringOrNull( $value ) {
		if ( null === $value || is_array( $value ) || is_object( $value ) || is_bool( $value ) ) {
			return null;
		}
		$value = trim( (string) $value );

		return '' === $value ? null : $value;
	}

	private static function intOrNull( $value ) {
		if ( is_bool( $value ) ) {
			return null;
		}

		return is_numeric( $value ) ? (int) $value : null;
	}

	private static function flagInt( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? 1 : 0;
		}

		return is_numeric( $value ) ? ( 0 !== (int) $value ? 1 : 0 ) : null;
	}

	private static function boolOrNull( $value ) {
		if ( null === $value ) {
			return null;
		}
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_numeric( $value ) ) {
			return 0 !== (int) $value;
		}
		$value = strtolower( trim( (string) $value ) );
		if ( 'true' === $value || 'false' === $value ) {
			return 'true' === $value;
		}

		return null;
	}

	private static function flagName( $value, $where ) {
		$value = self::stringOrNull( $value );
		if ( null === $value ) {
			return null;
		}

		$name = FlagFile::normalize( $value );
		if ( '' === $name ) {
			FlagFile::reportRejected( $value, $where );

			return null;
		}

		return $name;
	}

	private static function flagNames( $value, $where ) {
		if ( ! is_array( $value ) ) {
			return [];
		}
		$valid = [];
		foreach ( $value as $file ) {
			$file = self::flagName( $file, $where );
			if ( null !== $file ) {
				$valid[] = $file;
			}
		}

		return array_values( array_unique( $valid ) );
	}

	private static function prop( $entry, $key ) {
		if ( is_array( $entry ) ) {
			return isset( $entry[ $key ] ) ? $entry[ $key ] : null;
		}

		return isset( $entry->{$key} ) ? $entry->{$key} : null;
	}
}
