<?php

namespace WPML\UrlHandling;

use WPML\LIB\WP\Option;
use function WPML\Container\make;

class WPLoginUrlConverterRules implements \IWPML_Action {

	const MARKED_FOR_UPDATE_AND_VALIDATE_OR_ROLLBACK = 3;
	const MARKED_FOR_UPDATE = true;
	const UNMARKED = false;

	const SKIP_SAVING_LANG_IN_COOKIES_KEY = 'skip_saving_language_cookie';

	const UPDATE_RULES_KEY = 'wpml_login_page_translation_update_rules';

	public function add_hooks() {
		add_filter( 'wpml_should_skip_saving_language_in_cookies', function ( $forceSkipSavingLangInCookies ) {
			if ( $forceSkipSavingLangInCookies ) {
				return true;
			}

			return isset( $_GET[ WPLoginUrlConverterRules::SKIP_SAVING_LANG_IN_COOKIES_KEY ] )
			       && $_GET[ WPLoginUrlConverterRules::SKIP_SAVING_LANG_IN_COOKIES_KEY ] === 'true';
		} );
		
		if ( Option::getOr( self::UPDATE_RULES_KEY, self::UNMARKED ) && self::canWriteRulesFile() ) {
			add_filter( 'init', [ self::class, 'update' ] );
		}
	}

	public static function markRulesForUpdating( $verify = false ) {
		Option::update(
			self::UPDATE_RULES_KEY,
			$verify ? self::MARKED_FOR_UPDATE_AND_VALIDATE_OR_ROLLBACK : self::MARKED_FOR_UPDATE
		);
	}

	public static function update() {
		global $wp_rewrite;

		$loadedSetting             = self::getLoadedSetting();
		$isLoadedSettingComparable = true;
		if ( self::isSettingChangedSinceBoot( $loadedSetting ) ) {
			self::reloadSetting();
			if ( ! self::isSettingChangedSinceBoot( self::getLoadedSetting() ) ) {
				return;
			}

			$isLoadedSettingComparable = false;
		}

		self::loadRewriteFunctions();
		$wp_rewrite->rewrite_rules();
		save_mod_rewrite_rules();
		$wp_rewrite->flush_rules( false );

		$isValidationRequested = self::isValidationMark( Option::get( self::UPDATE_RULES_KEY ) )
			|| self::isValidationMark( Option::getRaw( self::UPDATE_RULES_KEY ) );
		Option::update( self::UPDATE_RULES_KEY, self::UNMARKED );

		if ( $isLoadedSettingComparable && self::isSettingChangedSinceBoot( $loadedSetting ) ) {
			if ( ! Option::getRaw( self::UPDATE_RULES_KEY ) ) {
				self::markRulesForUpdating( $isValidationRequested && (bool) Option::getRaw( WPLoginUrlConverter::SETTINGS_KEY ) );
			}
		} elseif ( $isValidationRequested ) {
			static::validateOrDisable();
		}
	}

	private static function canWriteRulesFile() {
		if ( ! wpml_is_cli() ) {
			return true;
		}

		self::loadRewriteFunctions();

		return got_mod_rewrite();
	}

	private static function loadRewriteFunctions() {
		if ( ! function_exists( 'save_mod_rewrite_rules' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}
	}

	private static function isValidationMark( $mark ) {
		return self::MARKED_FOR_UPDATE_AND_VALIDATE_OR_ROLLBACK === (int) $mark;
	}

	private static function isSettingChangedSinceBoot( $loadedSetting ) {
		return (bool) Option::getRaw( WPLoginUrlConverter::SETTINGS_KEY ) !== (bool) $loadedSetting;
	}

	private static function reloadSetting() {
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		wp_cache_delete( WPLoginUrlConverter::SETTINGS_KEY, 'options' );
		WPLoginUrlConverter::isEnabled();
	}

	private static function getLoadedSetting() {
		$loadedOptions = wp_load_alloptions();
		if ( array_key_exists( WPLoginUrlConverter::SETTINGS_KEY, $loadedOptions ) ) {
			return $loadedOptions[ WPLoginUrlConverter::SETTINGS_KEY ];
		}

		return wp_cache_get( WPLoginUrlConverter::SETTINGS_KEY, 'options' );
	}

	public static function validateOrDisable() {
		$translationLangs = \WPML\Setup\Option::getTranslationLangs();

		if ( empty( $translationLangs ) ) {
			return;
		}

		$urlConverter = make( \WPML_URL_Converter::class );
		$newUrl       = $urlConverter->convert_url( wp_login_url(), $translationLangs[0] );

		$loginResponseCode = wp_remote_retrieve_response_code( wp_remote_get( $newUrl, [
			'body' => [
				self::SKIP_SAVING_LANG_IN_COOKIES_KEY => 'true'
			]
		] ) );

		if ( 200 !== $loginResponseCode ) {
			WPLoginUrlConverter::disable();
		}
	}
}
