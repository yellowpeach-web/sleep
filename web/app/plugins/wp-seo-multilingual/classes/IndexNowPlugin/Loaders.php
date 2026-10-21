<?php

namespace WPML\WPSEO\IndexNowPlugin;

class Loaders {

	public static function get() {
		global $sitepress;

		$languageNegotiationType = (int) $sitepress->get_setting( 'language_negotiation_type' );

		return WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN === $languageNegotiationType ? [] : [ SubmitHooks::class ];
	}
}
