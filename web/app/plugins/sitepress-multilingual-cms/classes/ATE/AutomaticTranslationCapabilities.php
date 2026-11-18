<?php

namespace WPML\TM\ATE;

use WPML\FP\Fns;
use WPML\FP\Obj;
use WPML\Setup\Option;
use WPML\TM\API\ATE\CachedLanguageMappings;
use WPML\TM\API\ATE\LanguageMappings;
use WPML\TM\ATE\ClonedSites\ReconnectState;
use WPML\TM\Jobs\JobLog;
use WPML\TranslationPolicy\Policy\Policy;
use function WPML\FP\curryN;

class AutomaticTranslationCapabilities {

	const UNAVAILABLE_REASON_SAME_AS_DEFAULT = 'same-as-default';
	const UNAVAILABLE_REASON_MANUAL_ONLY = 'manual-only';

	public static function isAvailable() {
		return Policy::isAccountUsable();
	}

	public static function isLanguageEligible( $languageCode, $sourceLang = null ) {
		if ( ! self::isAvailable() ) {
			return false;
		}

		return CachedLanguageMappings::isCodeEligibleForAutomaticTranslations( $languageCode, $sourceLang );
	}

	public static function withCapabilityInfo( $languages = null, $sourceLang = null ) {
		$fn = curryN( 1, function ( $languages, $sourceLang = null ) {
			if ( ! self::isAvailable() ) {
				return Fns::map(
					Obj::addProp( 'can_be_translated_automatically', Fns::always( false ) ),
					$languages
				);
			}

			return CachedLanguageMappings::withCanBeTranslatedAutomatically( $languages, $sourceLang );
		} );

		return call_user_func_array( $fn, func_get_args() );
	}

	public static function unavailableReasonFor( $defaultCode ) {
		return function ( $lang ) use ( $defaultCode ) {
			$canBeTranslated = Obj::prop( 'can_be_translated_automatically', $lang );

			if ( null === $canBeTranslated || $canBeTranslated ) {
				return null;
			}

			return LanguageMappings::resolvesToSameAteLanguageAsDefault( (string) Obj::prop( 'code', $lang ), (string) $defaultCode )
				? self::UNAVAILABLE_REASON_SAME_AS_DEFAULT
				: self::UNAVAILABLE_REASON_MANUAL_ONLY;
		};
	}

	public static function shouldTranslateEverything() {
		return self::isAvailable() && Option::shouldTranslateEverything();
	}

	public static function shouldTranslateEverythingFresh() {
		global $wpdb;
		$rawValue = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				'WPML(' . Option::OPTION_GROUP . ')'
			)
		);
		if ( ! $rawValue ) {
			return false;
		}
		$data = maybe_unserialize( $rawValue );
		if ( ! is_array( $data ) || ! isset( $data[ Option::TRANSLATE_EVERYTHING ] ) ) {
			return false;
		}
		return (bool) $data[ Option::TRANSLATE_EVERYTHING ];
	}


	public static function getEligibleLanguageCodes( $sourceLang = null ): array {
		if ( ! self::isAvailable() ) {
			return [];
		}

		$codes = LanguageMappings::geCodesEligibleForAutomaticTranslations( $sourceLang );

		if ( ! $codes && ReconnectState::isReconnecting() ) {
			$codes = CachedLanguageMappings::geCodesEligibleForAutomaticTranslations( $sourceLang );
			JobLog::add( 'auto_translate_eligible_from_day_cache_while_reconnecting', [
				'source_lang'      => $sourceLang,
				'target_languages' => array_values( $codes ),
			] );
		}

		return $codes;
	}

	public static function doesDefaultLanguageSupport() {
		if ( ! self::isAvailable() ) {
			return false;
		}

		return CachedLanguageMappings::doesDefaultLanguageSupportAutomaticTranslations();
	}
}
