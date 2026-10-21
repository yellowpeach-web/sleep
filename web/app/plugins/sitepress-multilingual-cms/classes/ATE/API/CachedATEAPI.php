<?php

namespace WPML\TM\ATE\API;

use WPML\Element\API\Languages;
use WPML\FP\Fns;
use WPML\FP\Lst;
use WPML\FP\Maybe;
use WPML\FP\Obj;
use WPML\LanguageEditor\LanguageCodeResolution;
use WPML\TM\API\ATE\LanguageMappings;
use WPML\TM\ATE\API\CacheStorage\StaticVariable;
use WPML\TM\ATE\API\CacheStorage\Storage;
use WPML\TM\ATE\API\CacheStorage\Transient;
use function WPML\FP\curryN;

class CachedATEAPI {

	const CACHE_OPTION = 'wpml-tm-ate-api-cache';

	const ENTRY_TTL = Transient::TTL;

	const REQUEST_MEMO_OPTION = 'wpml-tm-ate-api-cache-request';

	const NOTHING_MARKER = '__wpml_ate_nothing__';

	private $ateAPI;

	private $storage;

	private $cachedFns = [ 'get_languages_supported_by_automatic_translations', 'get_language_details', 'get_language_mapping' ];

	public function __construct( \WPML_TM_ATE_API $ateAPI, Storage $storage ) {
		$this->ateAPI = $ateAPI;
		$this->storage = $storage;
	}

	public function __call( $name, $args ) {
		return Lst::includes( $name, $this->cachedFns ) ? $this->callWithCache( $name, $args ) : call_user_func_array( [ $this->ateAPI, $name ], $args );
	}

	private function callWithCache( $fnName, $args ) {
		$key = $this->getKey( $args );

		$stored = $this->readLiveEntry( $this->storage, self::CACHE_OPTION, $fnName, $key );
		if ( null !== $stored && $this->hasAnswerShape( $fnName, $stored['value'] ) ) {
			return Maybe::of( $stored['value'] );
		}

		$memo = $this->readLiveEntry( StaticVariable::getInstance(), self::REQUEST_MEMO_OPTION, $fnName, $key );
		if ( null !== $memo ) {
			return self::NOTHING_MARKER === $memo['value']
				? Maybe::nothing()
				: Maybe::of( $memo['value'] );
		}

		if ( \WPML_TM_ATE_API::isUnreachableWindowLive() ) {
			$this->memoValue( $fnName, $key, self::NOTHING_MARKER );

			return Maybe::nothing();
		}

		$answer = call_user_func_array( [ $this->ateAPI, $fnName ], $args );

		$this->memoValue( $fnName, $key, Fns::isNothing( $answer ) ? self::NOTHING_MARKER : $answer->get() );

		return $answer->map(
			function ( $result ) use ( $fnName, $args ) {
				if ( $this->isResultCacheable( $fnName, $args, $result ) ) {
					$this->cacheValue( $fnName, $args, $result );
				}

				return $result;
			}
		);
	}

	private function memoValue( $fnName, $key, $value ) {
		$this->writeEntry( StaticVariable::getInstance(), self::REQUEST_MEMO_OPTION, $fnName, $key, $value );
	}

	private function readLiveEntry( Storage $storage, $option, $fnName, $key ) {
		$entries = $this->readEntries( $storage, $option );

		if ( ! isset( $entries[ $fnName ][ $key ] ) || ! $this->isLive( $entries[ $fnName ][ $key ], time() ) ) {
			return null;
		}

		return $entries[ $fnName ][ $key ];
	}

	private function readEntries( Storage $storage, $option ) {
		$data = $storage->get( $option, [] );

		if (
			! is_array( $data )
			|| ! isset( $data['scope'], $data['entries'] )
			|| ! is_array( $data['entries'] )
			|| ! self::scopeCovers( $data['scope'] )
		) {
			return [];
		}

		return $data['entries'];
	}

	private static function scopeCovers( $stamp ) {
		$current = self::cacheScope();

		return $stamp === $current || $stamp === strstr( $current, '|', true ) . '|';
	}

	private function writeEntry( Storage $storage, $option, $fnName, $key, $value ) {
		$now     = time();
		$entries = [];

		foreach ( $this->readEntries( $storage, $option ) as $storedFn => $byKey ) {
			foreach ( (array) $byKey as $storedKey => $entry ) {
				if ( $this->isLive( $entry, $now ) ) {
					$entries[ $storedFn ][ $storedKey ] = $entry;
				}
			}
		}

		$entries[ $fnName ][ $key ] = [
			'value'      => $value,
			'expires_at' => $now + self::ENTRY_TTL,
		];

		$storage->save( $option, [
			'scope'   => self::cacheScope(),
			'entries' => $entries,
		] );
	}

	private function isLive( $entry, $now ) {
		return is_array( $entry )
			&& array_key_exists( 'value', $entry )
			&& isset( $entry['expires_at'] )
			&& $entry['expires_at'] > $now;
	}

	public static function cacheScope() {
		$uuid = get_option( \WPML_Site_ID::SITE_ID_KEY . ':' . \WPML_TM_ATE::SITE_ID_SCOPE, '' );

		return ( new \WPML_TM_ATE_AMS_Endpoints() )->get_ATE_base_url() . '|' . ( is_string( $uuid ) ? $uuid : '' );
	}

	public function isResultCacheable( $fnName, $args, $result ) {
		if ( ! $this->hasAnswerShape( $fnName, $result ) ) {
			return false;
		}

		if ( 'get_languages_supported_by_automatic_translations' !== $fnName ) {
			return true;
		}

		$requestedCodes = isset( $args[0] ) && is_array( $args[0] ) ? $args[0] : [];
		$supportMap     = is_object( $result ) ? get_object_vars( $result ) : ( is_array( $result ) ? $result : [] );

		$sourceFolds = $this->getSourceFolds( $args );

		foreach ( $requestedCodes as $code ) {
			if ( in_array( strtolower( (string) $code ), $sourceFolds, true ) ) {
				continue;
			}
			if ( ! $this->isCodeSupported( (string) $code, $supportMap ) ) {
				return false;
			}
		}

		return true;
	}

	private function hasAnswerShape( $fnName, $answer ) {
		return 'get_language_mapping' !== $fnName || LanguageMappings::isMappingList( $answer );
	}

	private function getSourceFolds( array $args ) {
		$source = isset( $args[1] ) && $args[1] ? (string) $args[1] : (string) Languages::getDefaultCode();

		if ( '' === $source ) {
			return [];
		}

		return array_map( 'strtolower', LanguageCodeResolution::ateLookupChain( $source ) );
	}

	private function isCodeSupported( $code, array $supportMap ) {
		foreach ( LanguageCodeResolution::ateLookupChain( $code ) as $variant ) {
			$matchedKey = LanguageMappings::resolveVariantKey( $variant, $supportMap );
			if ( null !== $matchedKey && null !== $supportMap[ $matchedKey ] ) {
				return true;
			}
		}

		return false;
	}

	public function cacheValue( $fnName = null, $args = null, $result = null ) {
		$fn = curryN( 3, function ( $fnName, $args, $result ) {
			$this->writeEntry( $this->storage, self::CACHE_OPTION, $fnName, $this->getKey( $args ), $result );

			return $result;
		} );

		return call_user_func_array( $fn, func_get_args() );
	}

	public static function clearAllCaches() {
		StaticVariable::getInstance()->delete( self::CACHE_OPTION );
		StaticVariable::getInstance()->delete( self::REQUEST_MEMO_OPTION );
		( new Transient() )->delete( self::CACHE_OPTION );
	}

	private function getKey( $args ) {
		return \serialize( $args );
	}
}
