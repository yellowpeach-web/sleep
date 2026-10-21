<?php

namespace WPML\TM\ATE\Retranslation;

use WPML\FP\Either;

class InfoClient {

	const CACHE_TTL        = 25;
	const CACHE_KEY_PREFIX = 'wpml_ate_retranslation_info_';

	private $ateAPI;

	public function __construct( \WPML_TM_ATE_API $ateAPI ) {
		$this->ateAPI = $ateAPI;
	}

	public function get( ?int $requestId = null ) {
		$cacheKey = self::CACHE_KEY_PREFIX . ( $requestId ?: 'current' );
		$cached   = get_transient( $cacheKey );

		if ( is_array( $cached ) && array_key_exists( 'response', $cached ) ) {
			return Either::of( RetranslationInfo::fromResponse( $cached['response'] ) );
		}

		return $this->ateAPI->get_retranslation_info( $requestId )->map(
			function ( $response ) use ( $cacheKey ) {
				set_transient( $cacheKey, [ 'response' => $response ], self::CACHE_TTL );

				return RetranslationInfo::fromResponse( $response );
			}
		);
	}
}
