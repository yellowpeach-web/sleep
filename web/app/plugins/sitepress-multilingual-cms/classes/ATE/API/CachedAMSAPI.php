<?php

namespace WPML\TM\ATE\API;

use WPML\FP\Lst;
use WPML\FP\Maybe;
use WPML\TM\ATE\API\CacheStorage\Storage;
use WPML\TM\ATE\ClonedSites\ReconnectState;
use function WPML\FP\curryN;

class CachedAMSAPI {

	const CACHE_OPTION = 'wpml-tm-ams-api-cache';

	const FAILED_READ_TTL = 600;

	const FAILED_READ_MARKER = '__failed_read__';

	private $amsApi;

	private $storage;

	private $cachedFns = [ 'getGlossaryCount', 'getNormalizedSuggestionsCount', 'get_translation_engines' ];
	private $clearCacheFns = [ 'update_translation_engines' ];

	private $noCollectionAsReturnType = [ 'get_translation_engines' ];

	public function __construct( \WPML_TM_AMS_API $amsApi, Storage $storage ) {
		$this->amsApi  = $amsApi;
		$this->storage = $storage;
	}

	public function __call( $name, $args ) {
		if ( Lst::includes( $name, $this->clearCacheFns ) ) {
			$this->storage->delete( self::CACHE_OPTION );
		}
		return Lst::includes( $name, $this->cachedFns ) ? $this->callWithCache( $name, $args ) : call_user_func_array( [ $this->amsApi, $name ], $args );
	}

	private function callWithCache( $fnName, $args ) {
		$data = $this->storage->get( self::CACHE_OPTION, [] );
		$key  = $this->getKey( $args );

		$hasEntry = array_key_exists( $fnName, $data ) && array_key_exists( $key, $data[ $fnName ] );

		if ( $hasEntry && Lst::includes( $fnName, $this->noCollectionAsReturnType ) ) {
			$entry = $data[ $fnName ][ $key ];
			if ( $this->isLiveReadEntry( $entry ) ) {
				return $this->isFailedReadEntry( $entry ) ? $entry['value'] : $entry;
			}
			$hasEntry = false;
		}

		if ( ! $hasEntry ) {
			$answer = call_user_func_array( [ $this->amsApi, $fnName ], $args );

			if ( Lst::includes( $fnName, $this->noCollectionAsReturnType ) ) {
				return ReconnectState::isReconnecting()
					? $answer
					: $this->cacheReadAnswer( $fnName, $args, $answer );
			}

			return $answer->map( $this->cacheValue( $fnName, $args ) );
		}

		return Maybe::of( $data[ $fnName ][ $key ] );
	}

	private function isLiveReadEntry( $entry ) {
		if ( $this->isFailedReadEntry( $entry ) ) {
			return $entry['expires_at'] > time();
		}

		return is_array( $entry );
	}

	private function isFailedReadEntry( $entry ) {
		return is_array( $entry )
			&& array_key_exists( self::FAILED_READ_MARKER, $entry )
			&& array_key_exists( 'expires_at', $entry );
	}

	private function cacheReadAnswer( $fnName, $args, $answer ) {
		if ( is_array( $answer ) ) {
			return $this->cacheValue( $fnName, $args, $answer );
		}

		$cachedData = $this->storage->get( self::CACHE_OPTION, [] );

		$cachedData[ $fnName ][ $this->getKey( $args ) ] = [
			self::FAILED_READ_MARKER => true,
			'value'                  => $answer,
			'expires_at'             => time() + self::FAILED_READ_TTL,
		];
		$this->storage->save( self::CACHE_OPTION, $cachedData );

		return $answer;
	}

	public function cacheValue( $functionName = null, $args = null, $result = null ) {
		$function = curryN( 3, function ( $functionName, $args, $result ) {
			$cachedData                                            = $this->storage->get( self::CACHE_OPTION, [] );
			$cachedData[ $functionName ][ $this->getKey( $args ) ] = $result;
			$this->storage->save( self::CACHE_OPTION, $cachedData );

			return $result;
		} );

		return call_user_func_array( $function, func_get_args() );
	}

	private function getKey( $parameters ) {
		return \serialize( $parameters );
	}


	public static function clearCache() {
		$storage = new \WPML\TM\ATE\API\CacheStorage\Transient();
		$storage->delete( self::CACHE_OPTION );
	}
}
