<?php

namespace WPML\TM\API\ATE;

use WPML\Element\API\Languages;
use WPML\FP\Either;
use WPML\FP\Fns;
use WPML\FP\Logic;
use WPML\FP\Lst;
use WPML\FP\Maybe;
use WPML\FP\Obj;
use WPML\FP\Relation;
use WPML\FP\Wrapper;
use WPML\Element\API\Entity\LanguageMapping;
use WPML\Core\Component\LanguageEditor\Domain\LanguageDerivation;
use WPML\LanguageEditor\LanguageCodeResolution;
use WPML\Setup\Option;
use WPML\TM\ATE\API\CacheStorage\StaticVariable;
use WPML\TM\ATE\API\CachedATEAPI;
use function WPML\Container\make;
use function WPML\FP\curryN;
use function WPML\FP\invoke;
use function WPML\FP\pipe;

class LanguageMappings {
	const IGNORE_MAPPING_ID = - 1;

	public static function getAllLanguagesWithAutomaticSupportInfo( $sourceLang = null ): array {
		return static::withCanBeTranslatedAutomatically( Languages::getActive(), $sourceLang );
	}

	public static function doesDefaultLanguageSupportAutomaticTranslations(): bool {
		$languages = static::getAllLanguagesWithAutomaticSupportInfo();

		$default = $languages[ Languages::getDefaultCode() ] ?? null;
		if ( $default ) {
			return true === Obj::prop( 'can_be_translated_automatically', $default );
		}

		return false;
	}

	public static function withCanBeTranslatedAutomatically( $targetLanguages = null, $sourceLang = null ) {
		if ( 0 === func_num_args() ) {
			return function ( $targetLanguages, $sourceLang = null ) {
				return static::withCanBeTranslatedAutomatically( $targetLanguages, $sourceLang );
			};
		}

		if ( ! is_object( $targetLanguages ) && ! is_array( $targetLanguages ) ) {
			return $targetLanguages;
		}

		$ateAPI = static::getATEAPI();

		$sourceLanguageCode = $sourceLang && \WPML\TM\ATE\AutomaticPairSupport::isRefusalEnabled()
			? (string) $sourceLang
			: (string) Languages::getDefaultCode();

		$mappedTargets = array_merge(
			static::mappedTargetsOfCustomRowsOnCatalogueCodes( [ [ 'code' => $sourceLanguageCode ] ] ),
			static::mappedTargetsOfCustomRowsOnCatalogueCodes( $targetLanguages )
		);

		$variantsByRow = [];
		$targetCodes   = [];
		foreach ( $targetLanguages as $key => $lang ) {
			$variantsByRow[ $key ] = static::lookupVariantsOf( $lang, $mappedTargets );
			$targetCodes           = array_merge( $targetCodes, $variantsByRow[ $key ] );
		}
		$targetCodes = array_values( array_unique( $targetCodes ) );

		$supportedLanguagesByATE = [];
		foreach ( static::sourceLookupVariants( $sourceLanguageCode, $mappedTargets ) as $i => $sourceVariant ) {
			$codesToAsk = 0 === $i
				? $targetCodes
				: array_values( array_diff( static::unreachedTargetCodes( $targetLanguages, $variantsByRow, $sourceLanguageCode, $supportedLanguagesByATE ), [ $sourceVariant ] ) );
			if ( ! $codesToAsk ) {
				break;
			}

			$ateAnswer = $ateAPI->get_languages_supported_by_automatic_translations( $codesToAsk, 0 === $i ? $sourceLang : $sourceVariant );

			if ( Fns::isNothing( $ateAnswer ) ) {
				if ( 0 === $i ) {
					return static::withUnknownAutomaticSupport( $targetLanguages );
				}
				continue;
			}

			$ateResponse = $ateAnswer->getOrElse( [] );
			if ( ! is_object( $ateResponse ) && ! is_array( $ateResponse ) ) {
				break;
			}

			foreach ( ( is_object( $ateResponse ) ? get_object_vars( $ateResponse ) : $ateResponse ) as $answeredCode => $answer ) {
				$knownKey = static::resolveVariantKey( $answeredCode, $supportedLanguagesByATE );
				if ( null === $knownKey ) {
					$supportedLanguagesByATE[ $answeredCode ] = $answer;
				} elseif ( static::isSupportedAnswer( $answer ) && ! static::isSupportedAnswer( $supportedLanguagesByATE[ $knownKey ] ) ) {
					$supportedLanguagesByATE[ $knownKey ] = $answer;
				}
			}
		}

		$hasAnySupportedLanguage = false;
		foreach ( $supportedLanguagesByATE as $supported ) {
			if ( null !== $supported ) {
				$hasAnySupportedLanguage = true;
				break;
			}
		}

		$result = is_object( $targetLanguages ) ? clone $targetLanguages : [];

		foreach ( $targetLanguages as $key => $lang ) {
			$code             = Obj::prop( 'code', $lang );
			$supportedVariant = null;
			foreach ( $variantsByRow[ $key ] as $variant ) {
				$matchedKey = static::resolveVariantKey( $variant, $supportedLanguagesByATE );
				if ( null !== $matchedKey && static::isSupportedAnswer( $supportedLanguagesByATE[ $matchedKey ] ) ) {
					$supportedVariant = $matchedKey;
					break;
				}
			}
			$answer = null !== $supportedVariant ? $supportedLanguagesByATE[ $supportedVariant ] : null;
			$engine = is_object( $answer ) ? ( $answer->engine ?? null ) : null;

			if ( $code === $sourceLanguageCode ) {
				$canAutoTranslate = $hasAnySupportedLanguage;

				if ( ! $canAutoTranslate ) {
					$languageDetails  = $ateAPI->get_language_details( $code )->getOrElse( [] );
					$canAutoTranslate =
						(bool) Obj::prop( 'ms_api_iso', $languageDetails ) ||
						(bool) Obj::prop( 'google_api_iso', $languageDetails ) ||
						(bool) Obj::prop( 'deepl_api_iso', $languageDetails );
				}
			} else {
				$canAutoTranslate = null !== $supportedVariant;
			}

			if ( is_array( $lang ) ) {
				$lang['engine']                          = $engine;
				$lang['can_be_translated_automatically'] = $canAutoTranslate;
				$lang['effective_ate_language_code']     = $supportedVariant;
				$result[ $key ]                          = $lang;
			} elseif ( is_object( $lang ) ) {
				$lang->engine                          = $engine;
				$lang->can_be_translated_automatically = $canAutoTranslate;
				$lang->effective_ate_language_code     = $supportedVariant;
				$result->{$key}                        = $lang;
			} else {
				if ( is_object( $result ) ) {
					$result->{$key} = $lang;
				} else {
					$result[ $key ] = $lang;
				}
			}
		}

		return $result;
	}

	protected static function withUnknownAutomaticSupport( $targetLanguages ) {
		$result = is_object( $targetLanguages ) ? clone $targetLanguages : [];

		foreach ( $targetLanguages as $key => $lang ) {
			if ( is_array( $lang ) ) {
				$lang['engine']                          = null;
				$lang['can_be_translated_automatically'] = null;
				$lang['effective_ate_language_code']     = null;
			} elseif ( is_object( $lang ) ) {
				$lang->engine                          = null;
				$lang->can_be_translated_automatically = null;
				$lang->effective_ate_language_code     = null;
			}

			if ( is_object( $result ) ) {
				$result->{$key} = $lang;
			} else {
				$result[ $key ] = $lang;
			}
		}

		return $result;
	}

	public static function isCodeEligibleForAutomaticTranslations( $languageCode = null, $sourceLang = null ) {
		$fn = Lst::includes( Fns::__, static::geCodesEligibleForAutomaticTranslations( $sourceLang ) );

		return call_user_func_array( $fn, func_get_args() );
	}

	public static function get() {
		return Fns::map( function ( $record ) {
			return new LanguageMapping(
				Obj::prop( 'source_code', $record ),
				Obj::path( [ 'source_language', 'name' ], $record ),
				Obj::path( [ 'target_language', 'id' ], $record ),
				Obj::prop( 'target_code', $record )
			);
		}, self::mappingRows( static::getATEAPI()->get_language_mapping()->getOrElse( [] ) ) );
	}

	public static function isMappingList( $answer ) {
		return is_array( $answer ) && array_values( $answer ) === $answer;
	}

	public static function mappingRows( $answer ) {
		if ( ! self::isMappingList( $answer ) ) {
			return [];
		}

		return array_values(
			array_filter(
				$answer,
				function ( $row ) {
					return ( is_array( $row ) || is_object( $row ) )
						&& is_string( Obj::prop( 'source_code', $row ) )
						&& '' !== Obj::prop( 'source_code', $row );
				}
			)
		);
	}

	public static function withMapping( $languages = null ) {
		$fn = curryN( 1, function ( $languages ) {
			$mapping           = self::get();
			$findMappingByCode = function ( $language ) use ( $mapping ) {
				return Lst::find( invoke( 'matches' )->with( Obj::prop( 'code', $language ) ), $mapping );
			};

			return Fns::map( Obj::addProp( 'mapping', $findMappingByCode ), $languages );
		} );

		return call_user_func_array( $fn, func_get_args() );
	}

	public static function getAvailable() {
		$mapping = static::getATEAPI()->get_available_languages();

		return Relation::sortWith( [ Fns::ascend( Obj::prop( 'name'  ) ) ], $mapping );
	}

	public static function supportedTargets( array $codes, $sourceLang = null ) {
		$codes = array_values( array_unique( array_map( 'strval', $codes ) ) );
		if ( ! $codes ) {
			return [];
		}

		$answer = static::getATEAPI()->get_languages_supported_by_automatic_translations( $codes, $sourceLang );
		if ( Fns::isNothing( $answer ) ) {
			return null;
		}

		$response   = $answer->getOrElse( [] );
		$supportMap = is_object( $response ) ? get_object_vars( $response ) : (array) $response;

		$out = [];
		foreach ( $codes as $code ) {
			$key          = static::resolveVariantKey( $code, $supportMap );
			$out[ $code ] = null !== $key && null !== $supportMap[ $key ] && false !== $supportMap[ $key ];
		}

		return $out;
	}


	public static function saveMapping( array $mappings ) {
		list( $mappings, $refused, $doubtful, $defaultOntoSecondary ) = static::partitionMappingsOntoDefaultLanguage( $mappings );
		$ontoDefault = array_values(
			array_filter(
				$refused,
				function ( $mapping ) use ( $defaultOntoSecondary ) {
					return ! in_array( $mapping, $defaultOntoSecondary, true );
				}
			)
		);
		if ( $ontoDefault ) {
			static::logMappings( $ontoDefault, __( 'A language mapping onto the default language was not sent to ATE.', 'sitepress' ) );
		}
		if ( $defaultOntoSecondary ) {
			static::logMappings( $defaultOntoSecondary, __( 'A mapping of the default language onto one of the site\'s other languages was not sent to ATE.', 'sitepress' ) );
		}
		if ( $doubtful ) {
			static::logMappings( $doubtful, static::doubtfulMappingsLogText() );
		}

		list( $ignoredMapping, $mappingSet ) = \wpml_collect( $mappings )->partition( Relation::propEq( 'targetId', self::IGNORE_MAPPING_ID ) );

		$ignoredCodes = $ignoredMapping->pluck( 'sourceCode' )->toArray();

		$ateAPI = static::getATEAPI();
		if ( count( $ignoredCodes ) ) {
			$ateAPI->get_language_mapping()
			       ->map( [ self::class, 'mappingRows' ] )
			       ->map( Fns::filter( pipe( Obj::prop( 'source_code' ), Lst::includes( Fns::__, $ignoredCodes ) ) ) )
			       ->map( Lst::pluck( 'id' ) )
			       ->filter( Logic::complement( Logic::isEmpty() ) )
			       ->map( [ $ateAPI, 'remove_language_mapping' ] );
		}

		$result = $ateAPI->create_language_mapping( $mappingSet->values()->toArray() );

		CachedATEAPI::clearAllCaches();

		return $result;
	}

	const VERDICT_SEND = 'send';

	const VERDICT_REFUSE = 'refuse';

	const VERDICT_DOUBT = 'doubt';

	public static function isOntoDefaultLanguage( $sourceCode, $targetCode, $defaultCode = null ): bool {
		return self::VERDICT_REFUSE === static::ontoDefaultLanguageVerdict( $sourceCode, $targetCode, $defaultCode );
	}

	public static function ontoDefaultLanguageVerdict( $sourceCode, $targetCode, $defaultCode = null ): string {
		$defaultCode = null === $defaultCode ? static::defaultCodeOrEmpty() : (string) $defaultCode;
		$source      = static::foldCode( $sourceCode );
		$target      = static::foldCode( $targetCode );
		$default     = static::foldCode( $defaultCode );

		if ( '' === $source || '' === $target || '' === $default || $source === $default ) {
			return self::VERDICT_SEND;
		}

		$defaultHead = static::headOf( $default );
		if ( static::headOf( $target ) !== $defaultHead ) {
			return self::VERDICT_SEND;
		}

		$sourceHead = static::confidentHeadOf( $source );
		if ( null === $sourceHead ) {
			return self::VERDICT_DOUBT;
		}

		return $sourceHead !== $defaultHead ? self::VERDICT_REFUSE : self::VERDICT_SEND;
	}

	public static function defaultOntoSecondaryVerdict( $sourceCode, $targetCode, $defaultCode = null, $secondaryCodes = null ): string {
		$defaultCode = null === $defaultCode ? static::defaultCodeOrEmpty() : (string) $defaultCode;
		$source      = static::foldCode( $sourceCode );
		$target      = static::foldCode( $targetCode );
		$default     = static::foldCode( $defaultCode );

		if ( '' === $source || '' === $target || '' === $default || $source !== $default ) {
			return self::VERDICT_SEND;
		}

		$defaultHead = static::headOf( $default );
		$targetHead  = static::headOf( $target );
		if ( $targetHead === $defaultHead ) {
			return self::VERDICT_SEND;
		}

		$doubt = false;
		foreach ( null === $secondaryCodes ? static::secondaryCodesOrEmpty() : (array) $secondaryCodes as $secondaryCode ) {
			$secondary = static::foldCode( $secondaryCode );
			if ( '' === $secondary || $secondary === $default ) {
				continue;
			}

			$secondaryHead = static::confidentHeadOf( $secondary );
			if ( null === $secondaryHead ) {
				$doubt = $doubt || static::headOf( $secondary ) === $targetHead;
				continue;
			}

			if ( $secondaryHead === $targetHead && $secondaryHead !== $defaultHead ) {
				return self::VERDICT_REFUSE;
			}
		}

		return $doubt ? self::VERDICT_DOUBT : self::VERDICT_SEND;
	}

	public static function partitionMappingsOntoDefaultLanguage( array $mappings, $defaultCode = null, $secondaryCodes = null ): array {
		$defaultCode          = null === $defaultCode ? static::defaultCodeOrEmpty() : (string) $defaultCode;
		$kept                 = [];
		$refused              = [];
		$doubtful             = [];
		$defaultOntoSecondary = [];

		foreach ( $mappings as $mapping ) {
			$verdict   = self::VERDICT_SEND;
			$ofDefault = self::VERDICT_SEND;
			if ( is_object( $mapping ) || is_array( $mapping ) ) {
				$source    = (string) Obj::propOr( '', 'sourceCode', $mapping );
				$target    = (string) Obj::propOr( '', 'targetCode', $mapping );
				$verdict   = static::ontoDefaultLanguageVerdict( $source, $target, $defaultCode );
				$ofDefault = static::defaultOntoSecondaryVerdict( $source, $target, $defaultCode, $secondaryCodes );
			}

			if ( self::VERDICT_REFUSE === $ofDefault ) {
				$refused[]              = $mapping;
				$defaultOntoSecondary[] = $mapping;
				continue;
			}
			if ( self::VERDICT_REFUSE === $verdict ) {
				$refused[] = $mapping;
				continue;
			}

			$kept[] = $mapping;
			if ( self::VERDICT_DOUBT === $verdict || self::VERDICT_DOUBT === $ofDefault ) {
				$doubtful[] = $mapping;
			}
		}

		return [ $kept, $refused, $doubtful, $defaultOntoSecondary ];
	}

	public static function swappedPairPartners( array $mappings, array $ontoDefault, $defaultCode = null ): array {
		$default = static::foldCode( null === $defaultCode ? static::defaultCodeOrEmpty() : (string) $defaultCode );
		if ( '' === $default ) {
			return [];
		}

		$mappedOntoDefault = [];
		foreach ( $ontoDefault as $mapping ) {
			$head = static::confidentHeadOf( static::foldCode( Obj::propOr( '', 'sourceCode', $mapping ) ) );
			if ( null !== $head ) {
				$mappedOntoDefault[ $head ] = true;
			}
		}
		if ( ! $mappedOntoDefault ) {
			return [];
		}

		$defaultHead = static::headOf( $default );
		$partners    = [];
		foreach ( $mappings as $key => $mapping ) {
			if ( ( ! is_object( $mapping ) && ! is_array( $mapping ) ) || static::foldCode( Obj::propOr( '', 'sourceCode', $mapping ) ) !== $default ) {
				continue;
			}

			$target = static::foldCode( Obj::propOr( '', 'targetCode', $mapping ) );
			if ( '' === $target ) {
				continue;
			}

			$targetHead = static::headOf( $target );
			if ( $targetHead !== $defaultHead && isset( $mappedOntoDefault[ $targetHead ] ) ) {
				$partners[] = $key;
			}
		}

		return $partners;
	}

	public static function doubtfulMappingsLogText(): string {
		return __( 'A language mapping onto the default language was kept because the mapped language could not be identified.', 'sitepress' );
	}

	public static function logMappings( array $mappings, string $description ) {
		if ( ! $mappings ) {
			return;
		}

		static::logLine(
			$description,
			[
				'mappings' => array_values(
					array_map(
						function ( $mapping ) {
							return [
								'source' => (string) Obj::propOr( Obj::propOr( '', 'source_code', $mapping ), 'sourceCode', $mapping ),
								'target' => (string) Obj::propOr( Obj::propOr( '', 'target_code', $mapping ), 'targetCode', $mapping ),
							];
						},
						$mappings
					)
				),
			]
		);
	}

	public static function logLine( string $description, array $extraData = [] ) {
		if ( ! function_exists( 'wpml_tm_ate_ams_log' ) || ! class_exists( \WPML\TM\ATE\Log\Entry::class ) ) {
			return;
		}

		try {
			$entry              = new \WPML\TM\ATE\Log\Entry();
			$entry->eventType   = \WPML\TM\ATE\Log\EventsTypes::SERVER_ATE;
			$entry->description = $description;
			$entry->extraData   = $extraData;
			wpml_tm_ate_ams_log( $entry, true );
		} catch ( \Throwable $e ) {
		}
	}

	protected static function defaultCodeOrEmpty(): string {
		try {
			return (string) Languages::getDefaultCode();
		} catch ( \Throwable $e ) {
			return '';
		}
	}

	protected static function secondaryCodesOrEmpty(): array {
		try {
			return array_values( array_map( 'strval', (array) Languages::getSecondaryCodes() ) );
		} catch ( \Throwable $e ) {
			return [];
		}
	}

	private static function foldCode( $code ): string {
		return str_replace( '_', '-', strtolower( trim( (string) $code ) ) );
	}

	private static function headOf( string $code ): string {
		try {
			$head = LanguageCodeResolution::head( $code );
		} catch ( \Throwable $e ) {
			$head = $code;
		}

		return LanguageDerivation::languageHead( static::foldCode( '' === (string) $head ? $code : $head ) );
	}

	private static function confidentHeadOf( string $code ) {
		try {
			$head = LanguageCodeResolution::confidentHead( $code );
		} catch ( \Throwable $e ) {
			return null;
		}

		return null === $head || '' === (string) $head ? null : LanguageDerivation::languageHead( static::foldCode( $head ) );
	}

	public static function getLanguagesEligibleForAutomaticTranslations() {
		return Wrapper::of( Languages::getSecondaries() )
		              ->map( static::withCanBeTranslatedAutomatically() )
		              ->map( Fns::filter( Obj::prop( 'can_be_translated_automatically' ) ) )
		              ->get();
	}

	public static function geCodesEligibleForAutomaticTranslations( $sourceLang = null ): array {
		if ( $sourceLang ) {
			$eligible = Fns::filter(
				Obj::prop( 'can_be_translated_automatically' ),
				static::getAllLanguagesWithAutomaticSupportInfo( $sourceLang )
			);
			return array_diff( (array) Lst::pluck( 'code', $eligible ), [ $sourceLang ] );
		}
		return Lst::pluck( 'code', static::getLanguagesEligibleForAutomaticTranslations() );
	}


	public static function hasTheSameMappingAsDefaultLang( $language = null ) {
		$fn = curryN( 1, function ( $language ) {
			$defaultLanguage = Lst::last( static::withMapping( [ Languages::getDefault() ] ) );
			if ( ! is_object( $defaultLanguage ) && ! is_array( $defaultLanguage ) ) {
				return false;
			}
			$defaultLanguageMappingTargetCode = Obj::pathOr( Obj::prop( 'code', $defaultLanguage ), [ 'mapping', 'targetCode' ], $defaultLanguage );

			return Obj::pathOr( null, [ 'mapping', 'targetCode' ], $language ) === $defaultLanguageMappingTargetCode;
		} );

		try {
			$hasMapping = call_user_func_array( $fn, func_get_args() );
		} catch ( \InvalidArgumentException $e ) {
			$hasMapping = false;
		}

		return $hasMapping;
	}

	public static function resolvesToSameAteLanguageAsDefault( string $code, $defaultCode = null ): bool {
		$defaultCode = null === $defaultCode ? Languages::getDefaultCode() : (string) $defaultCode;

		if ( '' === $code || '' === $defaultCode || $code === $defaultCode ) {
			return false;
		}

		$shared = array_intersect(
			array_map( 'strtolower', static::getCodeVariants( $code ) ),
			array_map( 'strtolower', static::getCodeVariants( $defaultCode ) )
		);

		return [] !== $shared;
	}

	protected static function sourceLookupVariants( string $sourceCode, array $mappedTargets ): array {
		$chain = [ $sourceCode ];
		foreach ( static::lookupVariantsOf( [ 'code' => $sourceCode ], $mappedTargets ) as $variant ) {
			$variant = (string) $variant;
			if ( '' !== $variant && ! in_array( $variant, $chain, true ) ) {
				$chain[] = $variant;
			}
		}

		return $chain;
	}

	protected static function unreachedTargetCodes( $targetLanguages, array $variantsByRow, string $sourceLanguageCode, array $supportMap ): array {
		$codes = [];
		foreach ( $targetLanguages as $key => $lang ) {
			if ( (string) Obj::prop( 'code', $lang ) === $sourceLanguageCode || empty( $variantsByRow[ $key ] ) ) {
				continue;
			}
			foreach ( $variantsByRow[ $key ] as $variant ) {
				$matchedKey = static::resolveVariantKey( $variant, $supportMap );
				if ( null !== $matchedKey && static::isSupportedAnswer( $supportMap[ $matchedKey ] ) ) {
					continue 2;
				}
			}
			$codes = array_merge( $codes, $variantsByRow[ $key ] );
		}

		return array_values( array_unique( $codes ) );
	}

	protected static function isSupportedAnswer( $answer ): bool {
		return null !== $answer && false !== $answer;
	}

	protected static function getLookupVariants( $lang ): array {
		return static::getCodeVariants( (string) Obj::prop( 'code', $lang ) );
	}

	protected static function lookupVariantsOf( $lang, array $mappedTargets ): array {
		$key = strtolower( (string) Obj::prop( 'code', $lang ) );

		if ( '' !== $key && array_key_exists( $key, $mappedTargets ) ) {
			return null === $mappedTargets[ $key ] ? [] : [ (string) $mappedTargets[ $key ] ];
		}

		return static::getLookupVariants( $lang );
	}

	protected static function mappedTargetsOfCustomRowsOnCatalogueCodes( $targetLanguages ): array {
		$custom = [];
		foreach ( $targetLanguages as $lang ) {
			if ( ! is_array( $lang ) && ! is_object( $lang ) ) {
				continue;
			}
			$code = (string) Obj::prop( 'code', $lang );
			if ( '' !== $code && LanguageCodeResolution::isCustomOnCatalogueCode( $code ) ) {
				$custom[ strtolower( $code ) ] = null;
			}
		}

		if ( ! $custom ) {
			return [];
		}

		foreach ( static::storedMappingTargets() as $source => $target ) {
			if ( array_key_exists( $source, $custom ) ) {
				$custom[ $source ] = $target;
			}
		}

		return $custom;
	}

	protected static function storedMappingTargets(): array {
		$all = [];

		try {
			$all = (array) static::get();
		} catch ( \Throwable $e ) {
			$all = [];
		}

		try {
			$all = array_merge( $all, (array) Option::getLanguageMappings() );
		} catch ( \Throwable $e ) {
		}

		$out = [];
		foreach ( $all as $mapping ) {
			$mapping = is_array( $mapping ) ? (object) $mapping : $mapping;
			if ( ! is_object( $mapping ) ) {
				continue;
			}

			$source = isset( $mapping->sourceCode ) ? trim( (string) $mapping->sourceCode ) : '';
			$target = isset( $mapping->targetCode ) ? trim( (string) $mapping->targetCode ) : '';
			if ( '' === $source || '' === $target ) {
				continue;
			}

			$out[ str_replace( '_', '-', strtolower( $source ) ) ] = $target;
		}

		return $out;
	}

	public static function resolveVariantKey( $variant, array $supportMap ) {
		if ( array_key_exists( $variant, $supportMap ) ) {
			return (string) $variant;
		}

		$lowered = strtolower( (string) $variant );
		foreach ( array_keys( $supportMap ) as $key ) {
			if ( strtolower( (string) $key ) === $lowered ) {
				return (string) $key;
			}
		}

		return null;
	}

	protected static function getCodeVariants( string $code ): array {
		return LanguageCodeResolution::ateLookupChain( $code );
	}

	protected static function getATEAPI() {
		return new CachedATEAPI( make( \WPML_TM_ATE_API::class ), StaticVariable::getInstance() );
	}
}
