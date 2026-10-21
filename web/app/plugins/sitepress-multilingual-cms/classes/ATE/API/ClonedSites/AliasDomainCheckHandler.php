<?php

namespace WPML\TM\ATE\ClonedSites;

use WPML\Request\Policy\Policy;
use WPML\Request\Policy\Registry;

class AliasDomainCheckHandler implements \IWPML_Frontend_Action, \IWPML_Backend_Action, \IWPML_DIC_Action {

	const GET_PARAM     = 'wpml_alias_domain_check';
	const OPTION_KEY    = 'wpml_alias_domain_check_token';
	const RESPONSE_BODY = 'wpml-alias-domain-check-ok';

	const EXPECTED_OPTION_KEY = 'wpml_alias_domain_check_expected';

	const EXPECTED_TTL = 120;

	const ROUTE = 'alias-domain-check';

	public function add_hooks() {
		if ( isset( $_GET[ self::GET_PARAM ] ) ) {
			add_action( 'init', [ $this, 'handleCheck' ], 1 );
		}
	}

	public function handleCheck() {
		$token = isset( $_GET[ self::GET_PARAM ] ) && is_string( $_GET[ self::GET_PARAM ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::GET_PARAM ] ) ) : '';

		if ( ! self::policy()->permits( $token ) ) {
			return;
		}

		delete_option( self::EXPECTED_OPTION_KEY );
		update_option( self::OPTION_KEY, $token, 'no' );

		wp_die( self::RESPONSE_BODY, '', [ 'response' => 200 ] );
	}

	public static function policy() {
		return Registry::declare(
			Registry::PSEUDO_ROUTE,
			self::ROUTE,
			Policy::machine(
				[ self::class, 'isExpectedToken' ],
				'alias-domain loopback probe: the sha256 of the token AliasDomainProber recorded before probing, unexpired'
			)
		);
	}

	public static function expect( $token ) {
		update_option(
			self::EXPECTED_OPTION_KEY,
			[
				'hash'    => hash( 'sha256', (string) $token ),
				'expires' => time() + self::EXPECTED_TTL,
			],
			'no'
		);
	}

	public static function isExpectedToken( $token ) {
		if ( ! is_string( $token ) || '' === $token ) {
			return false;
		}

		$expected = get_option( self::EXPECTED_OPTION_KEY );
		if ( ! is_array( $expected ) || empty( $expected['hash'] ) || ! is_string( $expected['hash'] ) ) {
			return false;
		}
		if ( empty( $expected['expires'] ) || time() > (int) $expected['expires'] ) {
			return false;
		}

		return hash_equals( $expected['hash'], hash( 'sha256', $token ) );
	}

	public static function getAndDeleteToken() {
		$token = get_option( self::OPTION_KEY, '' );
		delete_option( self::OPTION_KEY );

		return $token;
	}
}
