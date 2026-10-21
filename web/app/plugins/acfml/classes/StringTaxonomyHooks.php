<?php

namespace ACFML;

use SitePress;
use WPML\FP\Str;

class StringTaxonomyHooks implements \IWPML_Backend_Action {

	const TAXONOMY_SINGULAR_NAME_PREFIX = 'taxonomy singular name: ';
	const TAXONOMY_GENERAL_NAME_PREFIX  = 'taxonomy general name: ';

	private $sitepress;

	public function __construct( SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		add_filter(
			'wpml_taxonomy_strings_source_language',
			[ $this, 'acfmlTaxonomyStringsSourceLanguage' ],
			10,
			3
		);
	}

	public function acfmlTaxonomyStringsSourceLanguage( $sourceLang, $text, $name ) {
		$acfTaxonomies       = wp_list_pluck( acf_get_acf_taxonomies(), 'labels' );
		$acfTaxonomiesValues = [];

		if ( Str::startsWith( self::TAXONOMY_SINGULAR_NAME_PREFIX, $name ) ) {
			$acfTaxonomiesValues = wp_list_pluck( $acfTaxonomies, 'singular_name' );
		} elseif ( Str::startsWith( self::TAXONOMY_GENERAL_NAME_PREFIX, $name ) ) {
			$acfTaxonomiesValues = wp_list_pluck( $acfTaxonomies, 'name' );
		}

		if ( in_array( $text, $acfTaxonomiesValues, true ) ) {
			return $this->sitepress->get_default_language();
		}

		return $sourceLang;
	}
}
