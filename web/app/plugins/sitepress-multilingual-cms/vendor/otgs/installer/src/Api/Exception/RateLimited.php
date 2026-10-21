<?php

namespace OTGS\Installer\Api\Exception;

class RateLimited extends \Exception {

	const KEY = 'retry_after';

	public function __construct( $details ) {
		parent::__construct( (string) $details );
	}

	public static function answers( $apiResponse ) {
		if ( ! is_object( $apiResponse ) ) {
			return false;
		}

		if ( ! empty( $apiResponse->{self::KEY} ) ) {
			return true;
		}

		return isset( $apiResponse->error ) && 1 === preg_match( '/^rate limited\b/i', (string) $apiResponse->error );
	}

	public static function sentence( $apiResponse ) {
		if ( is_object( $apiResponse ) && ! empty( $apiResponse->error ) ) {
			return (string) $apiResponse->error;
		}

		return __( 'Too many requests. Retry in a minute.', 'installer' );
	}
}
