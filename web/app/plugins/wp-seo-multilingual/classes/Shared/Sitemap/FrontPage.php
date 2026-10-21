<?php

namespace WPML\WPSEO\Shared\Sitemap;

use WPML\Element\API\PostTranslations;

class FrontPage {

	public static function getIdsByLanguage() {
		if ( ! self::showsStaticPageOnFront() ) {
			return [];
		}

		$pageId = (int) get_option( 'page_on_front', 0 );

		if ( ! $pageId ) {
			return [];
		}

		$ids = [];

		foreach ( (array) PostTranslations::get( $pageId ) as $translation ) {
			$ids[ $translation->language_code ] = (int) $translation->element_id;
		}

		return $ids;
	}

	private static function showsStaticPageOnFront(): bool {
		return 'page' === get_option( 'show_on_front' );
	}
}
