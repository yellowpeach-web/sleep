<?php

namespace WPML\WPSEO\RankMathSEO\Sitemap;

use RankMath\Sitemap\Generator;
use RankMath\Sitemap\Sitemap;
use WPML\WPSEO\Shared\Sitemap\BaseAlternateLangHooks;
use function WPML\Container\make;

class TranslatedArchivesHooks implements \IWPML_Frontend_Action, \IWPML_DIC_Action {

	private const POST_TYPES_WITH_OWN_FIRST_LINKS = [ 'post', 'page' ];

	private $sitepress;

	private $postType;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		add_action( 'parse_request', [ $this, 'addSitemapHooks' ] );
	}

	public function addSitemapHooks( $wp ) {
		$postType = $wp->query_vars['sitemap'] ?? null;

		if ( $this->hasTranslatedArchive( $postType ) ) {
			$this->postType = $postType;
			add_filter( 'rank_math/sitemap/' . $postType . '_content', [ $this, 'appendTranslatedArchives' ] );
		}
	}

	public function appendTranslatedArchives( $content ) {
		return $content . $this->getTranslatedArchiveEntries( (string) $this->postType );
	}

	private function hasTranslatedArchive( $postType ): bool {
		return is_string( $postType )
			&& ! in_array( $postType, self::POST_TYPES_WITH_OWN_FIRST_LINKS, true )
			&& post_type_exists( $postType )
			&& $this->sitepress->is_translated_post_type( $postType );
	}

	private function getTranslatedArchiveEntries( string $postType ): string {
		$archiveUrls        = $this->getArchiveUrlsByLanguage( $postType );
		$defaultArchiveUrl  = $archiveUrls[ $this->sitepress->get_default_language() ] ?? null;
		$translatedArchives = array_diff( $archiveUrls, [ $defaultArchiveUrl ] );

		if ( ! $translatedArchives ) {
			return '';
		}

		$lastModified = Sitemap::get_last_modified_gmt( $postType );
		$generator    = make( Generator::class );
		$entries      = '';

		foreach ( $translatedArchives as $archiveUrl ) {
			$entries .= $generator->sitemap_url(
				[
					'loc'                       => $archiveUrl,
					'mod'                       => $lastModified,
					BaseAlternateLangHooks::KEY => $archiveUrls,
				]
			);
		}

		return $entries;
	}

	private function getArchiveUrlsByLanguage( string $postType ): array {
		$archiveUrls = [];

		foreach ( array_keys( $this->sitepress->get_active_languages() ) as $language ) {
			$archiveUrl = $this->getArchiveUrlInLanguage( $postType, $language );

			if ( is_string( $archiveUrl ) && '' !== $archiveUrl ) {
				$archiveUrls[ $language ] = $archiveUrl;
			}
		}

		return $archiveUrls;
	}

	private function getArchiveUrlInLanguage( string $postType, string $language ) {
		$this->sitepress->switch_lang( $language );

		try {
			return apply_filters( 'rank_math/sitemap/post_type_archive_link', get_post_type_archive_link( $postType ), $postType );
		} finally {
			$this->sitepress->switch_lang();
		}
	}
}
