<?php

namespace WPML\Core;


use WPML\Collect\Support\Traits\Macroable;
use WPML\FP\Obj;
use function WPML\FP\curryN;
use function WPML\FP\partial;

class LanguageNegotiation {
	use Macroable;

	const DIRECTORY = 1;
	const DOMAIN = 2;
	const PARAMETER = 3;

	const SUNRISE_DOMAINS_CONSTANT = 'WPML_SUNRISE_MULTISITE_DOMAINS';

	const DIRECTORY_STRING = 'directory';
	const DOMAIN_STRING = 'domain';
	const PARAMETER_STRING = 'parameter';

	private static $modeMap = [
		self::DIRECTORY_STRING => self::DIRECTORY,
		self::DOMAIN_STRING    => self::DOMAIN,
		self::PARAMETER_STRING => self::PARAMETER,
	];

	public static function isDomainModeAvailable() {
		global $wpmu_version, $sitepress;

		if ( isset( $wpmu_version ) ) {
			return false;
		}

		$wpApi = $sitepress ? $sitepress->get_wp_api() : new \WPML_WP_API();

		if ( ! $wpApi->is_multisite() ) {
			return true;
		}

		return (bool) $wpApi->constant( self::SUNRISE_DOMAINS_CONSTANT );
	}

	public static function init() {
		global $sitepress;

		self::macro( 'saveMode', curryN( 1, function ( $mode ) use ( $sitepress ) {
			$mode = is_numeric( $mode )
				? (int) $mode
				: Obj::propOr( self::PARAMETER, $mode, self::$modeMap );

			$sitepress->set_setting( 'language_negotiation_type', $mode, true );
		} ) );

		self::macro( 'getMode', partial( [ $sitepress, 'get_setting' ], 'language_negotiation_type' ) );

		self::macro( 'getModeAsString', function ( $mode = null ) {
			return \wpml_collect( self::$modeMap )->flip()->get( $mode ?: self::getMode(), self::DIRECTORY_STRING );
		} );

		self::macro( 'saveDomains', curryN( 1, function ( $domains ) use ( $sitepress ) {
			$sitepress->set_setting( 'language_domains', $domains, true );
		} ) );

		self::macro( 'getDomains', partial( [ $sitepress, 'get_setting' ], 'language_domains' ) );

	}
}

LanguageNegotiation::init();
