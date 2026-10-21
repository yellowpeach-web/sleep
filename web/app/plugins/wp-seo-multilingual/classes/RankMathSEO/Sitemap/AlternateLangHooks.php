<?php

namespace WPML\WPSEO\RankMathSEO\Sitemap;

use WPML\WPSEO\RankMathSEO\Utils;
use WPML\WPSEO\Shared\Sitemap\BaseAlternateLangHooks;
use RankMath\Sitemap\Generator;

class AlternateLangHooks extends BaseAlternateLangHooks {

	private $sitemapType = '';

	public function add_hooks() {
		add_action(
			'parse_request',
			function ( $wp ) {
				if ( isset( $wp->query_vars['sitemap'] ) ) {
					$this->add_sitemap_hooks( $wp->query_vars['sitemap'] );
				}
			}
		);
	}

	public function add_sitemap_hooks( $type ) {
		$this->sitemapType = (string) $type;

		add_filter( 'rank_math/sitemap/' . $type . '_urlset', [ $this, 'addNamespace' ] );
		add_filter( 'rank_math/sitemap/' . $type . '_sitemap_url', [ $this, 'addAlternateLangDataToFirstLinks' ], 1, 2 );
		add_filter( 'rank_math/sitemap/entry', [ $this, 'addAlternateLangData' ], 10, 3 );
		add_filter( 'rank_math/sitemap/url', [ $this, 'insertAlternateLinks' ], 10, 2 );
		add_filter( 'rank_math/sitemap_url', [ $this, 'insertAlternateLinks' ], 10, 2 );
	}

	protected function getUtils() {
		return Utils::class;
	}

	public function addAlternateLangDataToFirstLinks( $url, $generator ) {
		global $wp_filter;

		if ( ! isset( $url[ self::KEY ] ) ) {
			$url = $this->addAlternateLangDataToFirstLinkOfPostType( $url, $this->sitemapType );
		}

		$countCallbacksPerPriority = function ( $callbacks ) {
			return count( $callbacks );
		};

		$totalCallbacks = wpml_collect( $wp_filter[ current_filter() ]->callbacks )
			->map( $countCallbacksPerPriority )
			->sum();

		return $totalCallbacks > 1 ? $url : $generator->sitemap_url( $url );
	}
}
