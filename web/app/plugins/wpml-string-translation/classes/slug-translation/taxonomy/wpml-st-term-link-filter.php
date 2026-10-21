<?php

class WPML_ST_Term_Link_Filter {

	const CACHE_GROUP = 'WPML_ST_Term_Link_Filter::replace_base_in_permalink_structure';

	private $sitepress;

	private $slug_records;

	private $cache_factory;

	private $tax_settings;

	public function __construct(
		WPML_Tax_Slug_Translation_Records $slug_records,
		SitePress $sitepress,
		WPML_WP_Cache_Factory $cache_factory,
		WPML_ST_Tax_Slug_Translation_Settings $tax_settings
	) {
		$this->slug_records  = $slug_records;
		$this->sitepress     = $sitepress;
		$this->cache_factory = $cache_factory;
		$this->tax_settings  = $tax_settings;
	}

	public function replace_slug_in_termlink( $termlink, $term ) {
		if ( ! $termlink || ! $this->sitepress->is_translated_taxonomy( $term->taxonomy ) ) {
			return $termlink;
		}

		$term_lang  = $this->sitepress->get_language_for_element( $term->term_taxonomy_id, 'tax_' . $term->taxonomy );
		$cache_key  = $termlink . $term_lang;
		$cache_item = $this->cache_factory->create_cache_item( self::CACHE_GROUP, $cache_key );

		if ( $cache_item->exists() ) {
			$termlink = $cache_item->get();
		} else {
			if ( $this->tax_settings->is_translated( $term->taxonomy ) ) {
				$termlink = $this->replace_base( $termlink, $term->taxonomy, $term_lang );
			}

			$cache_item->set( $termlink );
		}

		return $termlink;
	}

	private function replace_base( $termlink, $taxonomy, $term_lang ) {
		$original_slug = $this->slug_records->get_original( $taxonomy );
		if ( ! $original_slug ) {
			return $termlink;
		}

		$translated_slug = $this->slug_records->get_translation( $taxonomy, $term_lang );
		$target          = $translated_slug ?: $original_slug;

		foreach ( array_unique( array_filter( [ $this->get_registered_slug( $taxonomy ), $original_slug ] ) ) as $base ) {
			if ( $base === $target ) {
				continue;
			}

			$replaced = $this->replace_slug( $termlink, $base, $target );
			if ( $replaced !== $termlink ) {
				return $replaced;
			}
		}

		return $termlink;
	}

	private function get_registered_slug( $taxonomy ) {
		$taxonomy_object = get_taxonomy( $taxonomy );
		if ( ! $taxonomy_object || ! is_array( $taxonomy_object->rewrite ) || empty( $taxonomy_object->rewrite['slug'] ) ) {
			return '';
		}

		return trim( (string) $taxonomy_object->rewrite['slug'], '/' );
	}

	private function replace_slug( $termlink, $original_slug, $translated_slug ) {
		if ( preg_match( '#/?' . preg_quote( $original_slug, '#' ) . '/#', $termlink ) ) {
			$termlink = preg_replace(
				'#^(/?)(' . preg_quote( $original_slug, '#' ) . ')/#',
				'$1' . str_replace( [ '\\', '$' ], [ '\\\\', '\\$' ], $translated_slug ) . '/',
				$termlink
			);
		}

		return $termlink;
	}
}
