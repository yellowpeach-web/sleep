<?php

namespace WPML\TM\ATE;

final class ReturnToken {

	const PARAM          = 'wpml_ate_return';
	const MESSAGE_PREFIX = 'ate-return|';

	public static function generate( $wpmlJobId, $userId ) {
		$wpmlJobId = (int) $wpmlJobId;
		$userId    = (int) $userId;
		if ( $wpmlJobId <= 0 || $userId <= 0 ) {
			return '';
		}

		$salt = wp_salt( 'auth' );
		if ( ! is_string( $salt ) || strlen( $salt ) < 32 ) {
			return '';
		}

		return hash_hmac( 'sha256', self::MESSAGE_PREFIX . $wpmlJobId . '|' . $userId, $salt );
	}

	public static function isValid( $wpmlJobId, $userId, $token ) {
		$expected = self::generate( $wpmlJobId, $userId );

		return '' !== $expected
			&& is_string( $token )
			&& hash_equals( $expected, $token );
	}

	public static function sign( $returnUrl, $wpmlJobId ) {
		$returnUrl = (string) $returnUrl;
		$token     = self::generate( $wpmlJobId, get_current_user_id() );

		if ( '' === $token || '' === $returnUrl ) {
			return $returnUrl;
		}

		return \WPML\TM\ATE\Hooks\ReturnCommand::url( $returnUrl, $token );
	}

	public static function verifyRequest( $wpmlJobId ) {
		if ( ! isset( $_GET[ self::PARAM ] ) || ! is_string( $_GET[ self::PARAM ] ) ) {
			return false;
		}

		$token = sanitize_text_field( wp_unslash( $_GET[ self::PARAM ] ) );

		return self::isValid( $wpmlJobId, get_current_user_id(), $token );
	}
}
