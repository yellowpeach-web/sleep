<?php

namespace WPML\WPSEO\Shared\IndexNow;

abstract class SiteHomeUrlSwitch {

	const PRIORITY_LAST = PHP_INT_MAX;

	private $openSiteHomeUrlScopes = 0;

	public function useSiteHomeUrl() {
		$this->openSiteHomeUrlScopes++;

		add_filter( 'wpml_get_home_url', [ $this, 'keepSiteHomeUrl' ], self::PRIORITY_LAST, 2 );
	}

	public function useLanguageHomeUrl() {
		$this->openSiteHomeUrlScopes = max( 0, $this->openSiteHomeUrlScopes - 1 );

		if ( 0 === $this->openSiteHomeUrlScopes ) {
			remove_filter( 'wpml_get_home_url', [ $this, 'keepSiteHomeUrl' ], self::PRIORITY_LAST );
		}
	}

	public function keepSiteHomeUrl( $languageHomeUrl, $siteHomeUrl ) {
		return $siteHomeUrl;
	}
}
