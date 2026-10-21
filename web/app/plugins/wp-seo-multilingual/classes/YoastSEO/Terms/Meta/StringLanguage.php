<?php

namespace WPML\WPSEO\YoastSEO\Terms\Meta;

use SitePress;

class StringLanguage {

	private $sitepress;

	private $wpdb;

	public function __construct( SitePress $sitepress, \wpdb $wpdb ) {
		$this->sitepress = $sitepress;
		$this->wpdb      = $wpdb;
	}

	public function getTermLanguage( $termTaxonomyId, $taxonomy ) {
		$language = $this->sitepress->get_language_for_element( (int) $termTaxonomyId, 'tax_' . $taxonomy );

		return is_string( $language ) && '' !== $language ? $language : null;
	}

	public function apply( array $languageByStringName ) {
		if ( ! $languageByStringName || ! class_exists( \WPML_ST_String_Factory::class ) ) {
			return;
		}

		$in = wpml_prepare_in( array_keys( $languageByStringName ) );

		$strings = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT id, name, language FROM {$this->wpdb->prefix}icl_strings WHERE context = %s AND name IN ($in)",
				Hooks::stringContext()
			)
		);

		$factory    = \WPML\Container\make( \WPML_ST_String_Factory::class );
		$changedIds = [];
		foreach ( (array) $strings as $string ) {
			$language = $languageByStringName[ $string->name ] ?? null;
			if ( $language && $language !== $string->language ) {
				$factory->find_by_id( (int) $string->id )->set_language( $language );
				$changedIds[] = (int) $string->id;
			}
		}

		if ( $changedIds ) {
			do_action( 'wpml_st_language_of_strings_changed', $changedIds );
		}
	}
}
