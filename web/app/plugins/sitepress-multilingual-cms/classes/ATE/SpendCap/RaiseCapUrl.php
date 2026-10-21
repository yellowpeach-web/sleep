<?php

namespace WPML\TM\ATE\SpendCap;

class RaiseCapUrl {

	const BASE_URL = 'https://app.wpml.org/account/words';

	const KEY = 'raise_cap_url';


	public static function apply( array $balances, array $credits = [] ) {
		if ( ! empty( $balances[ self::KEY ] ) && is_string( $balances[ self::KEY ] ) ) {
			return $balances;
		}

		$fromCredits = isset( $credits['site_spend_cap'][ self::KEY ] ) ? $credits['site_spend_cap'][ self::KEY ] : '';
		if ( is_string( $fromCredits ) && '' !== $fromCredits ) {
			$balances[ self::KEY ] = $fromCredits;

			return $balances;
		}

		$url = self::build();
		if ( '' === $url ) {
			unset( $balances[ self::KEY ] );

			return $balances;
		}

		$balances[ self::KEY ] = $url;

		return $balances;
	}


	public static function build() {
		$uuid = self::siteUuid();
		if ( '' === $uuid ) {
			return '';
		}

		$url = (string) add_query_arg( [ 'spend_cap_site' => $uuid ], self::BASE_URL );

		if ( class_exists( '\WPML\OutboundLinks\OutboundLinks' ) ) {
			return \WPML\OutboundLinks\OutboundLinks::to(
				$url,
				[
					'medium'   => 'notice',
					'campaign' => 'automatic-translation',
					'content'  => 'raise-cap',
				]
			);
		}

		return $url;
	}


	private static function siteUuid() {
		$authentication = \WPML\Container\make( \WPML_TM_ATE_Authentication::class );
		if ( ! is_object( $authentication ) || ! method_exists( $authentication, 'get_site_id' ) ) {
			return '';
		}

		$uuid = $authentication->get_site_id();

		return is_string( $uuid ) ? $uuid : '';
	}
}
