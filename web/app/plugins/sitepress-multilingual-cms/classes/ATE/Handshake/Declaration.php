<?php

namespace WPML\TM\ATE\Handshake;

class Declaration {

	const PARAM  = 'handshake';
	const VALUE  = 'level1';
	const HEADER = 'X-WPML-Request-Id';

	private static $requestId = null;

	private static $depth = 0;

	public static function begin( $requestId = null ) {
		if ( 0 === self::$depth ) {
			self::$requestId = $requestId ? (string) $requestId : self::mintRequestId();
		}

		self::$depth++;

		return (string) self::$requestId;
	}

	public static function end() {
		self::$depth = max( 0, self::$depth - 1 );

		if ( 0 === self::$depth ) {
			self::$requestId = null;
		}
	}

	public static function active() {
		return self::$depth > 0;
	}

	public static function declares( $url ) {
		if ( self::active() ) {
			return true;
		}

		return \WPML_TM_ATE_AMS_Endpoints::SERVICE_ATE === \WPML_TM_ATE_AMS_Endpoints::serviceOfUrl( (string) $url );
	}

	public static function requestId() {
		return self::$requestId;
	}

	public static function mintRequestId() {
		return wp_generate_uuid4();
	}

	public static function resetForTests() {
		self::$depth     = 0;
		self::$requestId = null;
	}
}
