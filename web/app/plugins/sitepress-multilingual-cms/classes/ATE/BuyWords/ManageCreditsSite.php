<?php

namespace WPML\TM\ATE\BuyWords;

class ManageCreditsSite {

	const OPTION = 'wpml_ate_manage_credits_site';

	public static function apply( array $balances ) {
		$pair = self::pairFrom( isset( $balances['redirect_url'] ) ? $balances['redirect_url'] : '' );

		if ( $pair ) {
			self::remember( $pair );
		} else {
			$pair = self::get();
		}

		if ( ! $pair ) {
			return $balances;
		}

		$balances['site']  = $pair['site'];
		$balances['token'] = $pair['token'];

		return $balances;
	}

	public static function get() {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) || empty( $stored['site'] ) || empty( $stored['token'] ) ) {
			return array();
		}

		return array(
			'site'  => (string) $stored['site'],
			'token' => (string) $stored['token'],
		);
	}

	private static function remember( array $pair ) {
		if ( $pair === self::get() ) {
			return;
		}

		update_option( self::OPTION, $pair, 'no' );
	}

	private static function pairFrom( $url ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return array();
		}

		$site  = self::readParam( $url, 'site' );
		$token = self::readParam( $url, 'token' );

		if ( '' === $site || '' === $token ) {
			return array();
		}

		return array(
			'site'  => $site,
			'token' => $token,
		);
	}

	private static function readParam( $url, $name ) {
		if ( ! preg_match( '/[?&]' . preg_quote( $name, '/' ) . '=([^&#]*)/', $url, $matches ) ) {
			return '';
		}

		return urldecode( $matches[1] );
	}
}
