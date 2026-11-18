<?php

namespace WPML\ST\AdminTexts;

use WPML_Displayed_String_Filter;
use WPML_ST_Package_String_Carriers;
use function WPML\Container\make;

class LeafTranslation {

	const CAN_QUEUE_FILTER = 'wpml_st_can_queue_custom_strings';

	private $dictionaries;

	private $locale;

	private $lookUpString;

	private $locales = [];

	private $carriedDomains;

	private $canQueue;

	private $hooked = false;

	public function __construct( ?TranslationFileDictionaries $dictionaries = null, ?\WPML_Locale $locale = null, ?callable $lookUpString = null ) {
		$this->dictionaries = $dictionaries;
		$this->locale       = $locale;
		$this->lookUpString = $lookUpString ?: [ self::class, 'lookUpStringInWpml' ];
	}

	public function translate( $domain, $stringName, $value, $lang ) {
		$dictionary = $this->dictionary( $domain, $lang );
		if (
			TranslationFileDictionaries::USE_STRING_LOOKUP === $dictionary
			|| ! $value
			|| ( 32 === strlen( $stringName ) && md5( (string) $value ) === $stringName )
		) {
			return call_user_func( $this->lookUpString, $domain, $stringName, $value, $lang );
		}

		if ( ! wpml_st_is_requested_blog() ) {
			return $value;
		}

		$normalizedName = WPML_Displayed_String_Filter::truncate_long_string( $stringName );
		$isHashed       = $normalizedName !== $stringName;

		if ( is_array( $dictionary ) ) {
			if ( isset( $dictionary[ $normalizedName ] ) && $dictionary[ $normalizedName ] !== $normalizedName ) {
				return $dictionary[ $normalizedName ];
			}
			if ( $isHashed && isset( $dictionary[ $stringName ] ) && $dictionary[ $stringName ] !== $stringName ) {
				return $dictionary[ $stringName ];
			}
		}

		if ( $isHashed ) {
			return call_user_func( $this->lookUpString, $domain, $stringName, $value, $lang );
		}

		if ( $this->canQueue() ) {
			do_action( 'wpml_st_add_to_queue', $value, $domain, '', $stringName );
		}

		return $value;
	}

	public function isCarried( $domain ) {
		$this->addHooks();

		if ( null === $this->carriedDomains ) {
			$this->carriedDomains = [];
			$carriers             = WPML_ST_Package_String_Carriers::get();
			if ( $carriers->hasAny() ) {
				foreach ( $carriers->live() as $claim ) {
					$this->carriedDomains[ (string) $claim['domain'] ] = true;
				}
			}
		}

		return isset( $this->carriedDomains[ $domain ] );
	}

	public function forgetCarriedDomains() {
		$this->carriedDomains = null;
	}

	public static function lookUpStringInWpml( $domain, $name, $value, $lang ) {
		$hasTranslation = null;

		return icl_translate( $domain, $name, $value, false, $hasTranslation, $lang );
	}

	private function dictionary( $domain, $lang ) {
		$locale = $this->localeOf( $lang );
		if ( ! $locale ) {
			return TranslationFileDictionaries::USE_STRING_LOOKUP;
		}

		if ( ! $this->dictionaries ) {
			$this->dictionaries = make( TranslationFileDictionaries::class );
		}

		return $this->dictionaries->get( $domain, $locale );
	}

	private function localeOf( $lang ) {
		if ( ! $lang ) {
			return false;
		}
		if ( ! array_key_exists( $lang, $this->locales ) ) {
			if ( ! $this->locale ) {
				$this->locale = make( \WPML_Locale::class );
			}
			$this->locales[ $lang ] = $this->locale->get_locale( $lang ) ?: false;
		}

		return $this->locales[ $lang ];
	}

	private function canQueue() {
		if ( null !== $this->canQueue ) {
			return $this->canQueue;
		}

		$canQueue = (bool) apply_filters( self::CAN_QUEUE_FILTER, false );
		if ( did_action( 'init' ) ) {
			$this->canQueue = $canQueue;
		}

		return $canQueue;
	}

	private function addHooks() {
		if ( $this->hooked ) {
			return;
		}
		$this->hooked = true;

		add_action( 'wpml_st_string_carriers_changed', [ $this, 'forgetCarriedDomains' ] );
	}
}
