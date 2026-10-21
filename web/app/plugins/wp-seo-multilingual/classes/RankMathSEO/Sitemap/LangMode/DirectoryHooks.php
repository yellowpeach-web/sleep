<?php

namespace WPML\WPSEO\RankMathSEO\Sitemap\LangMode;

use WPML\FP\Fns;
use WPML\FP\Lst;
use WPML\FP\Maybe;
use WPML\FP\Obj;
use WPML\FP\Relation;
use WPML\FP\Str;
use function WPML\FP\pipe;

class DirectoryHooks implements \IWPML_Frontend_Action, \IWPML_DIC_Action {

	private $sitepress;

	private $urlConverter;

	private $host;

	private $validLanguageDirs;

	public function __construct(
		\SitePress $sitepress,
		\WPML_URL_Converter $urlConverter
	) {
		$this->sitepress    = $sitepress;
		$this->urlConverter = $urlConverter;
	}

	public function add_hooks() {
		add_action( 'parse_query', [ $this, 'catchSitemapInSecondaryLanguage' ], - PHP_INT_MAX );

		if ( $this->isDefaultLangInDirectory() ) {
			add_filter( 'rank_math/links/is_external', [ $this, 'allowNonDefaultLangLinks' ], 10, 2 );
		}
	}

	public function catchSitemapInSecondaryLanguage( $wp_query ) {
		if (
			$wp_query->get( 'sitemap' )
			&& $this->sitepress->get_current_language() !== $this->sitepress->get_default_language()
		) {
			unset( $wp_query->query_vars['sitemap'] );
			$wp_query->set_404();
			status_header( 404 );
		}
	}

	public function allowNonDefaultLangLinks( $override, $urlParts ) {
		$isSameHost = pipe( Obj::prop( 'host' ), Relation::equals( $this->getHost() ) );

		$isDirectoryInActiveLang = pipe(
			Obj::propOr( '', 'path' ),
			Str::trim( '/' ),
			Str::split( '/' ),
			Lst::nth( 0 ),
			Lst::includes( Fns::__, $this->getValidLanguageDirs() )
		);

		$isValidUrlParts = (bool) Maybe::of( $urlParts )
			->filter( $isSameHost )
			->filter( $isDirectoryInActiveLang )
			->getOrElse( null );

		return $isValidUrlParts ? false : $override;
	}

	private function getHost() {
		if ( null === $this->host ) {
			$this->host = wpml_parse_url( $this->urlConverter->get_abs_home(), PHP_URL_HOST );
		}

		return $this->host;
	}

	private function getValidLanguageDirs() {
		if ( null === $this->validLanguageDirs ) {
			$this->validLanguageDirs = array_merge(
				[ '' ],
				array_keys( $this->sitepress->get_active_languages() )
			);
		}

		return $this->validLanguageDirs;
	}

	private function isDefaultLangInDirectory() {
		return (bool) Obj::path( [ 'urls', 'directory_for_default_language' ], $this->sitepress->get_settings() );
	}
}
