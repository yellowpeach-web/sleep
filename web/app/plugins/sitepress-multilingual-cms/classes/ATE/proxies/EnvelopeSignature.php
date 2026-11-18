<?php

namespace WPML\ATE\Proxies;

class EnvelopeSignature {

	public static function forForwardedBody( $url, $method, $receivedBody, $forwardedBody, $sharedKey, $secretKey ) {
		$url = (string) $url;
		if ( ! $sharedKey || ! $secretKey || (string) $receivedBody === (string) $forwardedBody ) {
			return $url;
		}

		$cut = strrpos( $url, '&signature=' );
		if ( false === $cut ) {
			return $url;
		}
		$unsigned = substr( $url, 0, $cut );

		$query = [];
		parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $query );
		if ( ! isset( $query['shared_key'], $query['signature'] ) || $query['shared_key'] !== $sharedKey
			|| ! is_string( $query['signature'] ) ) {
			return $url;
		}

		if ( ! hash_equals( self::sign( $method, $unsigned, (string) $receivedBody, $secretKey ), $query['signature'] ) ) {
			return $url;
		}

		return $unsigned . '&signature=' . rawurlencode( self::sign( $method, $unsigned, (string) $forwardedBody, $secretKey ) );
	}

	private static function sign( $method, $unsignedUrl, $body, $secret ) {
		$string = strtolower( (string) $method ) . $unsignedUrl
			. ( false === strpos( $unsignedUrl, '?' ) ? '?' : '&' ) . 'body=' . md5( $body );

		return base64_encode( hash_hmac( 'sha1', $string, (string) $secret, true ) );
	}
}
