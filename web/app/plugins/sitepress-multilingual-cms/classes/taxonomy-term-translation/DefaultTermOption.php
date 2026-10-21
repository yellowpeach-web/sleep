<?php

namespace WPML\TaxonomyTermTranslation;

class DefaultTermOption implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const OPTION_PREFIX = 'default_term_';

	private $sitepress;

	private $recreation;

	private $hooked = [];

	private $readingStoredRow = false;

	private $deletingDefaultTerm = [];

	private $termDelete;

	public function __construct( \SitePress $sitepress, ?DefaultTermRecreation $recreation = null, ?TermDeleteInFlight $termDelete = null ) {
		$this->sitepress  = $sitepress;
		$this->recreation = $recreation ? $recreation : new DefaultTermRecreation( $sitepress );
		$this->termDelete = $termDelete ? $termDelete : new TermDeleteInFlight();
	}

	public function add_hooks() {
		add_filter( 'register_taxonomy_args', [ $this, 'hookRegisteringTaxonomy' ], PHP_INT_MAX, 2 );
		add_action( 'pre_delete_term', [ $this, 'noteDefaultTermDelete' ], 10, 2 );
		add_action( 'delete_term', [ $this, 'recreateDeletedDefaultTerm' ], 10, 3 );

		foreach ( get_taxonomies( [], 'objects' ) as $name => $taxonomy ) {
			$this->hookTaxonomy( $name, $taxonomy );
		}
	}

	public function hookRegisteringTaxonomy( $args, $taxonomy ) {
		$this->hookTaxonomy( $taxonomy, $args );

		return $args;
	}

	public function hookTaxonomy( $taxonomy, $args ) {
		$taxonomy = (string) $taxonomy;
		$args     = (array) $args;

		if (
			isset( $this->hooked[ $taxonomy ] )
			|| empty( $args['default_term'] )
			|| ! $this->sitepress->is_translated_taxonomy( $taxonomy )
		) {
			return;
		}

		$this->hooked[ $taxonomy ] = true;

		$option = self::OPTION_PREFIX . $taxonomy;
		add_filter( 'option_' . $option, [ $this, 'translateRead' ], 10, 2 );
		add_filter( 'pre_update_option_' . $option, [ $this, 'normaliseWrite' ], 10, 3 );
	}

	public function translateRead( $value, $option ) {
		$termId = $this->termId( $value );

		if ( $this->readingStoredRow || ! $termId ) {
			return $value;
		}

		$taxonomy = $this->taxonomyOf( $option );
		$language = $this->termDelete->language( $taxonomy, $this->sitepress );
		$language = $language ? $language : $this->sitepress->get_current_language();
		if ( ! $language || 'all' === $language || $language === $this->sitepress->get_default_language() ) {
			return $value;
		}

		$translated = $this->sitepress->get_object_id( $termId, $taxonomy, false, $language );

		return $translated ? (string) $translated : $value;
	}

	public function normaliseWrite( $value, $oldValue, $option ) {
		$termId = $this->termId( $value );

		if ( ! $termId ) {
			return $value;
		}

		$taxonomy              = $this->taxonomyOf( $option );
		$defaultLanguageTermId = (int) $this->sitepress->get_object_id(
			$termId,
			$taxonomy,
			false,
			$this->sitepress->get_default_language()
		);

		if ( ! $defaultLanguageTermId ) {
			$defaultLanguageTermId = (int) $this->recreation->defaultLanguageMember( $termId, $taxonomy, $this->termId( $this->storedRow( $option ) ) );
		}

		if ( ! $defaultLanguageTermId ) {
			return $value;
		}

		if ( $defaultLanguageTermId === $this->termId( $this->storedRow( $option ) ) ) {
			return $oldValue;
		}

		return is_string( $value ) ? (string) $defaultLanguageTermId : $defaultLanguageTermId;
	}

	public function noteDefaultTermDelete( $termId, $taxonomy ) {
		$taxonomy = (string) $taxonomy;

		if ( ! isset( $this->hooked[ $taxonomy ] ) || (int) $termId !== $this->termId( $this->storedRow( self::OPTION_PREFIX . $taxonomy ) ) ) {
			return;
		}

		$this->deletingDefaultTerm[ $taxonomy ] = $this->recreation->tridOf( (int) $termId, $taxonomy );
	}

	public function recreateDeletedDefaultTerm( $termId, $ttId, $taxonomy ) {
		$taxonomy = (string) $taxonomy;

		if ( ! array_key_exists( $taxonomy, $this->deletingDefaultTerm ) ) {
			return;
		}

		$trid = $this->deletingDefaultTerm[ $taxonomy ];
		unset( $this->deletingDefaultTerm[ $taxonomy ] );

		$term = $this->recreation->writeDefaultLanguageTerm( $taxonomy, $trid );
		if ( ! $term ) {
			return;
		}

		$this->recreation->completeTranslations( $term['term_taxonomy_id'], $taxonomy );

		update_option( self::OPTION_PREFIX . $taxonomy, (string) $term['term_id'] );

		DefaultCategoryDeleteProtection::resetProtectedTermIds();
	}

	private function storedRow( $option ) {
		$this->readingStoredRow = true;

		try {
			return get_option( $option );
		} finally {
			$this->readingStoredRow = false;
		}
	}

	private function termId( $value ) {
		return is_numeric( $value ) && (int) $value > 0 ? (int) $value : 0;
	}

	private function taxonomyOf( $option ) {
		return (string) substr( (string) $option, strlen( self::OPTION_PREFIX ) );
	}
}
