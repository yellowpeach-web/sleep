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

		$mappedTargets = static::mappedTargetsOfCustomRowsOnCatalogueCodes( $targetLanguages );

		$targetCodes = [];
		foreach ( $targetLanguages as $lang ) {
			$targetCodes = array_merge( $targetCodes, static::lookupVariantsOf( $lang, $mappedTargets ) );
		}
		$targetCodes = array_values( array_unique( $targetCodes ) );

		$ateAnswer = $ateAPI->get_languages_supported_by_automatic_translations( $targetCodes, $sourceLang );

		if ( Fns::isNothing( $ateAnswer ) ) {
			return static::withUnknownAutomaticSupport( $targetLanguages );
		}

		$ateResponse        = $ateAnswer->getOrElse( [] );
		$supportedLanguagesByATE = is_object( $ateResponse ) ? get_object_vars( $ateResponse ) : ( is_array( $ateResponse ) ? $ateResponse : [] );

		$sourceLanguageCode = Languages::getDefaultCode();

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
			foreach ( static::lookupVariantsOf( $lang, $mappedTargets ) as $variant ) {
				$matchedKey = static::resolveVariantKey( $variant, $supportedLanguagesByATE );
				if (
					null !== $matchedKey
					&& null !== $supportedLanguagesByATE[ $matchedKey ]
					&& false !== $supportedLanguagesByATE[ $matchedKey ]
				) {
					$supportedVariant = $matchedKey;
					break;
				}
			}
			$engine = $supportedVariant && is_object( $ateResponse ) && isset( $ateResponse->{$supportedVariant} )
				? ( $ateResponse->{$supportedVariant}->engine ?? null )
				: null;

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
