<?php

class WPML_Allowed_Redirect_Hosts extends WPML_SP_User {

	public function __construct( &$sitepress ) {
		parent::__construct( $sitepress );
	}

	public function get_hosts( $hosts ) {
		$domains          = $this->sitepress->get_setting( 'language_domains' );
		$domains          = is_array( $domains ) ? $domains : array();
		$default_language = $this->sitepress->get_default_language();
		$default_home     = $this->sitepress->convert_url( $this->sitepress->get_wp_api()->get_home_url(), $default_language );

		if ( ! isset( $domains[ $default_language ] ) ) {
			$domains[ $default_language ] = wpml_parse_url( (string) $default_home, PHP_URL_HOST );
		}

		$active_languages = $this->sitepress->get_active_languages();

		foreach ( $domains as $code => $domain ) {
			if ( empty( $active_languages[ $code ] ) ) {
				continue;
			}

			$host = WPML_Language_Domains::hostOf( (string) $domain );

			if ( $host && ! in_array( $host, $hosts, true ) ) {
				$hosts[] = $host;
			}
		}

		return $hosts;
	}
}
