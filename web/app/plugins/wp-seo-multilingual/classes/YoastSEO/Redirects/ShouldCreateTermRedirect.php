<?php

namespace WPML\WPSEO\YoastSEO\Redirects;

use WPML\WPSEO\YoastSEO\Utils;

class ShouldCreateTermRedirect implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	const BEFORE_DETECT_SLUG_CHANGE_PRIORITY = 9;

	private $sitepress;

	private $editedTerm;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		add_action( 'edited_term', [ $this, 'captureEditedTerm' ], self::BEFORE_DETECT_SLUG_CHANGE_PRIORITY, 3 );
		Utils::add_filter( 'wpseo_premium_term_redirect_slug_change', [ $this, 'skipWhenTermUrlIsUnchanged' ] );
	}

	public function captureEditedTerm( $termId, $termTaxonomyId, $taxonomy ) {
		$this->editedTerm = [
			'termId'         => (int) $termId,
			'termTaxonomyId' => (int) $termTaxonomyId,
			'taxonomy'       => $taxonomy,
		];
	}

	public function skipWhenTermUrlIsUnchanged( $skip ) {
		if ( true === $skip || null === $this->editedTerm ) {
			return $skip;
		}

		$postedOldUrl = $this->getPostedOldUrl();
		if ( '' === $postedOldUrl ) {
			return $skip;
		}

		return $this->getPath( $postedOldUrl ) === $this->getTermLinkPath() ? true : $skip;
	}

	private function getPostedOldUrl(): string {
		return isset( $_POST['wpseo_old_term_url'] ) ? (string) wp_unslash( $_POST['wpseo_old_term_url'] ) : '';
	}

	private function getTermLinkPath() {
		$termLink = get_term_link( $this->editedTerm['termId'], $this->editedTerm['taxonomy'] );
		if ( ! is_string( $termLink ) ) {
			return null;
		}

		return $this->stripLanguageHomePath( $this->getPath( $termLink ) );
	}

	private function stripLanguageHomePath( string $termPath ): string {
		$language = $this->sitepress->get_language_for_element(
			$this->editedTerm['termTaxonomyId'],
			'tax_' . $this->editedTerm['taxonomy']
		);

		if ( ! $language ) {
			return $termPath;
		}

		$languageHomePath = $this->getPath( $this->sitepress->language_url( $language ) );

		if ( '' !== $languageHomePath && strpos( $termPath . '/', $languageHomePath . '/' ) === 0 ) {
			return trim( substr( $termPath, strlen( $languageHomePath ) ), '/' );
		}

		return $termPath;
	}

	private function getPath( string $url ): string {
		return trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
	}
}
