<?php

namespace WPML\WPSEO\RankMathSEO\RestHead;

use WPML\WPSEO\Shared\RestHead\BaseLanguageHooks;

class LanguageHooks extends BaseLanguageHooks {

	const HEAD_ROUTE = '/rankmath/v1/getHead';

	protected function getHeadRoute() {
		return self::HEAD_ROUTE;
	}

	protected function onLanguageSwitched() {
		rank_math()->settings->reset();

		remove_filter( 'home_url', [ $this, 'getHomeUrlOfCurrentLanguage' ] );

		if ( $this->isLanguageSwitched() && $this->isLanguagePerDomain() ) {
			add_filter( 'home_url', [ $this, 'getHomeUrlOfCurrentLanguage' ] );
		}
	}

	public function getHomeUrlOfCurrentLanguage( string $url ): string {
		return (string) apply_filters( 'wpml_permalink', $url, $this->sitepress->get_current_language() );
	}

	private function isLanguagePerDomain(): bool {
		return WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN === (int) $this->sitepress->get_setting( 'language_negotiation_type' );
	}
}
