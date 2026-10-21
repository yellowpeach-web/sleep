<?php

namespace WPML\WPSEO\YoastSEO\Sitemap;

use WPML\Element\API\Languages;
use WPML\WPSEO\Shared\Sitemap\BaseAlternateLangHooks;
use WPML\WPSEO\YoastSEO\Utils;

class AlternateLangHooks extends BaseAlternateLangHooks {

	public function add_hooks() {
		add_filter( 'wpseo_sitemap_urlset', [ $this, 'addNamespace' ] );
		add_filter( 'wpseo_sitemap_entry', [ $this, 'addAlternateLangData' ], 10, 3 );
		add_filter( 'wpseo_sitemap_url', [ $this, 'insertAlternateLinks' ], 10, 2 );
		add_filter( 'wpseo_sitemap_post_type_first_links', [ $this, 'addAlternateLangDataToFirstLinks' ], 10, 2 );
	}

	protected function getUtils() {
		return Utils::class;
	}

	public function addAlternateLangDataToFirstLinks( $links, $postType = '' ) {
		foreach ( $links as &$link ) {
			$link = $this->addAlternateLangDataToFirstLinkOfPostType( $link, (string) $postType );
		}

		return $links;
	}
}
