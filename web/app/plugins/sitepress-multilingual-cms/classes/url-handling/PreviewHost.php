<?php

namespace WPML\UrlHandling;

class PreviewHost {

	public static function toRequestHost( string $url ): string {
		$request_host = self::getRequestHost();

		if ( '' === $request_host ) {
			return $url;
		}

		if ( ! preg_match( '#^((?:[a-z][a-z0-9+.\-]*:)?//)([^/?\#]*)#i', $url, $matches ) ) {
			return $url;
		}

		$authority = $matches[2];
		$at        = strrpos( $authority, '@' );
		$user_info = false === $at ? '' : substr( $authority, 0, $at + 1 );
		$host_port = false === $at ? $authority : substr( $authority, $at + 1 );

		if ( '' === $host_port || strtolower( $host_port ) === strtolower( $request_host ) ) {
			return $url;
		}

		return $matches[1] . $user_info . $request_host . substr( $url, strlen( $matches[0] ) );
	}

	public static function isDomainMode(): bool {
		return WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN === (int) apply_filters( 'wpml_setting', 0, 'language_negotiation_type' );
	}

	private static function getRequestHost(): string {
		if ( ! isset( $_SERVER['HTTP_HOST'] ) ) {
			return '';
		}

		$host = filter_var(
			wp_unslash( $_SERVER['HTTP_HOST'] ),
			FILTER_VALIDATE_REGEXP,
			[ 'options' => [ 'regexp' => '/^(?:[a-z0-9._-]+|\[[0-9a-f:.]+\])(?::\d{1,5})?\z/i' ] ]
		);

		return is_string( $host ) ? $host : '';
	}
}
