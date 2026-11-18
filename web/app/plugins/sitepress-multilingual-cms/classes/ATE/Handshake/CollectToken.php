<?php

namespace WPML\TM\ATE\Handshake;

class CollectToken {

	const TRANSIENT = 'wpml_ate_handshake_collect_token';

	const TTL = 120;

	public static function mint() {
		$token = wp_generate_password( 32, false, false );

		set_transient( self::TRANSIENT, wp_hash( $token ), self::TTL );

		return $token;
	}

	public static function consumeToken( $token ) {
		$expected = get_transient( self::TRANSIENT );

		$matches = is_string( $expected )
			&& is_string( $token )
			&& '' !== $token
			&& hash_equals( $expected, wp_hash( $token ) );

		if ( $matches ) {
			delete_transient( self::TRANSIENT );
		}

		return $matches;
	}
}
