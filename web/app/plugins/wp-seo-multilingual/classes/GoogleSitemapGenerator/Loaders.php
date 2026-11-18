<?php

namespace WPML\WPSEO\GoogleSitemapGenerator;

class Loaders {

	public static function get() {
		return self::canCoreHandOver() ? [ Hooks::class ] : [];
	}

	private static function canCoreHandOver() {
		return ! class_exists( \WPML_Google_Sitemap_Generator::class )
			|| defined( \WPML_Google_Sitemap_Generator::class . '::HANDED_OVER_FILTER' );
	}
}
