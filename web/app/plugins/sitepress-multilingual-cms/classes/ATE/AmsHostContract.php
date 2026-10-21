<?php

namespace WPML\TM\ATE;

use WPML\Core\SharedKernel\Component\WpmlOrgClient\Domain\WpmlOrgOrigin;
use WPML\OutboundLinks\OutboundLinks;
use WPML\TM\ATE\BuyWords\ManageCreditsSite;

class AmsHostContract {

	const VERSION = 2;

	const BUY_WORDS_URL = 'https://app.wpml.org/account/words/buy';

	const WORDS_PAGE_URL = 'https://app.wpml.org/account/words';

	const ACCOUNT_URL = 'https://app.wpml.org/account';

	const PRICING_URL = 'https://wpml.org/documentation/automatic-translation/automatic-translation-pricing/';

	const CAMPAIGN = [
		'medium'   => 'ui',
		'campaign' => 'billing-page',
	];

	public static function dependencies() {
		return [
			'sitepress-multilingual-cms' => [
				'version'       => defined( 'ICL_SITEPRESS_VERSION' ) ? ICL_SITEPRESS_VERSION : '',
				'host_contract' => self::VERSION,
			],
		];
	}

	public static function seedLinks() {
		return self::links( ManageCreditsSite::get(), WpmlOrgOrigin::configured() );
	}

	public static function links( array $pair, WpmlOrgOrigin $estate ) {
		$site  = isset( $pair['site'] ) ? (string) $pair['site'] : '';
		$token = isset( $pair['token'] ) ? (string) $pair['token'] : '';

		return [
			'buy_words'  => self::door( self::BUY_WORDS_URL, self::sitePair( $site, $token ), $estate ),
			'words_page' => self::door(
				self::WORDS_PAGE_URL,
				array_merge( [ 'manage_credits' => '1' ], self::sitePair( $site, $token ) ),
				$estate
			),
			'account'    => self::door( self::ACCOUNT_URL, [], $estate ),
			'pricing'    => self::door( self::PRICING_URL, [], $estate ),
		];
	}

	private static function sitePair( $site, $token ) {
		if ( '' === $site || '' === $token ) {
			return [];
		}

		return [
			'site'  => $site,
			'token' => $token,
		];
	}

	private static function door( $url, array $params, WpmlOrgOrigin $estate ) {
		$segments = [];
		foreach ( $params as $name => $value ) {
			$segments[] = rawurlencode( (string) $name ) . '=' . rawurlencode( (string) $value );
		}

		if ( $segments ) {
			$url .= '?' . implode( '&', $segments );
		}

		return $estate->mapUrl( OutboundLinks::to( $url, self::CAMPAIGN ) );
	}
}
