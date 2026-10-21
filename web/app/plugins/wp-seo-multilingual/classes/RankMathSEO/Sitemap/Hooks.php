<?php

namespace WPML\WPSEO\RankMathSEO\Sitemap;

use WPML\FP\Obj;
use WPML\WPSEO\Shared\Sitemap\FrontPage;
use WPML\WPSEO\Shared\Sitemap\HomeUrl;

class Hooks implements \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const SETTING_AUTO_ADJUST_IDS = 'auto_adjust_ids';

	private const SITEMAP_INDEX_TYPE = '1';

	private $sitepress;

	private $wpdb;

	private $homesById;

	private $autoAdjustIdsBeforeBuild = null;

	private $buildType = null;

	public function __construct( \SitePress $sitepress, \wpdb $wpdb ) {
		$this->sitepress = $sitepress;
		$this->wpdb      = $wpdb;
	}

	public function add_hooks() {
		add_filter( 'rank_math/sitemap/build_type', [ $this, 'disableAutoAdjustIds' ], 11 );
		add_filter( 'rank_math/sitemap/entry', [ $this, 'filterEntry' ], 10, 3 );
		add_filter( 'rank_math/html_sitemap/get_posts/join', [ $this, 'byLanguageJoin' ], 10, 2 );
		add_filter( 'rank_math/html_sitemap/get_posts/where', [ $this, 'byLanguageWhere' ], 10, 2 );
	}

	public function disableAutoAdjustIds( $type ) {
		if ( self::SITEMAP_INDEX_TYPE === $type || null !== $this->autoAdjustIdsBeforeBuild ) {
			return $type;
		}

		$autoAdjustIds = $this->sitepress->get_setting( self::SETTING_AUTO_ADJUST_IDS );

		if ( ! $autoAdjustIds ) {
			return $type;
		}

		$this->autoAdjustIdsBeforeBuild = $autoAdjustIds;
		$this->buildType                = $type;
		$this->sitepress->set_setting( self::SETTING_AUTO_ADJUST_IDS, 0 );

		foreach ( $this->getEntriesBuiltFilters( $type ) as $entriesBuiltFilter ) {
			add_filter( $entriesBuiltFilter, [ $this, 'restoreAutoAdjustIds' ], PHP_INT_MAX );
		}

		return $type;
	}

	public function restoreAutoAdjustIds( $value ) {
		if ( null !== $this->autoAdjustIdsBeforeBuild ) {
			$this->sitepress->set_setting( self::SETTING_AUTO_ADJUST_IDS, $this->autoAdjustIdsBeforeBuild );

			foreach ( $this->getEntriesBuiltFilters( $this->buildType ) as $entriesBuiltFilter ) {
				remove_filter( $entriesBuiltFilter, [ $this, 'restoreAutoAdjustIds' ], PHP_INT_MAX );
			}

			$this->autoAdjustIdsBeforeBuild = null;
			$this->buildType                = null;
		}

		return $value;
	}

	private function getEntriesBuiltFilters( $type ) {
		return [
			'rank_math/sitemap/' . $type . '_urlset',
			'rank_math/sitemap/' . $type . '/content',
		];
	}

	public function filterEntry( $url, $type, $obj ) {
		if ( $url && 'post' === $type ) {
			return $this->replaceHomePage( $url, $obj );
		}

		return $url;
	}

	private function replaceHomePage( $url, $obj ) {
		if ( null === $this->homesById ) {
			$this->homesById = [];

			foreach ( FrontPage::getIdsByLanguage() as $language => $id ) {
				$this->homesById[ $id ] = HomeUrl::get( $language );
			}
		}

		return Obj::assoc(
			'loc',
			Obj::propOr(
				$url['loc'],
				(int) Obj::prop( 'ID', $obj ),
				$this->homesById
			),
			$url
		);
	}

	public function byLanguageJoin( $sql, $postType ) {
		if ( $this->sitepress->is_translated_post_type( $postType ) ) {
			$sql .= PostTranslationsJoin::sql( $this->wpdb );
		}

		return $sql;
	}

	public function byLanguageWhere( $sql, $postType ) {
		$default_language = $this->sitepress->get_default_language();
		$current_language = $this->sitepress->get_current_language();

		if ( $this->sitepress->is_translated_post_type( $postType ) ) {
			$sql .= $this->wpdb->prepare(
				' AND ( wpml_translations.language_code = %s',
				$current_language
			);

			if ( $this->sitepress->is_display_as_translated_post_type( $postType ) ) {
				$sql .= $this->wpdb->prepare(
					"
					OR (
						wpml_translations.language_code = %s
						AND NOT EXISTS (
							SELECT 1
							FROM {$this->wpdb->posts} p1
							JOIN {$this->wpdb->prefix}icl_translations t1 ON p1.ID = t1.element_id
							WHERE t1.trid = wpml_translations.trid
							  AND t1.language_code = %s
							  AND p1.post_status = 'publish'
						)
					)
					",
					$default_language,
					$current_language
				);
			}

			$sql .= ' )';
		}

		return $sql;
	}
}
