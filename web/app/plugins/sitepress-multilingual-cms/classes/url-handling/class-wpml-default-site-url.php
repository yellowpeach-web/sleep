<?php

class WPML_Default_Site_Url {

	public static function get() {
		global $sitepress, $wpml_url_converter;

		if ( ! $sitepress instanceof SitePress || ! $wpml_url_converter instanceof WPML_URL_Converter ) {
			return get_site_url();
		}

		if ( WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN !== (int) $sitepress->get_setting( 'language_negotiation_type' ) ) {
			return get_site_url();
		}

		$default_site_url = filter_var( (string) $wpml_url_converter->get_default_site_url(), FILTER_SANITIZE_URL );

		return $default_site_url ? rtrim( $default_site_url, '/' ) : get_site_url();
	}

	public static function getHome() {
		global $sitepress, $wpml_url_converter;

		if (
			$sitepress instanceof SitePress
			&& $wpml_url_converter instanceof WPML_URL_Converter
			&& WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN === (int) $sitepress->get_setting( 'language_negotiation_type' )
		) {
			return self::get();
		}

		return get_home_url();
	}
}
