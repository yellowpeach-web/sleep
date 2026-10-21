<?php

class WPML_Tax_Permalink_Filters implements IWPML_Action {

	const CACHE_GROUP = 'icl_tax_permalink_filter';

	private $term_element_factory;

	private $url_converter;

	private $cache_factory;

	private $ls_languages_status;

	private $language_negotiation_type;

	private $debug_backtrace;

	public function __construct(
		WPML_URL_Converter $url_converter,
		WPML_WP_Cache_Factory $cache_factory,
		WPML_Translation_Element_Factory $term_element_factory,
		WPML_Get_LS_Languages_Status $ls_language_status,
		$language_negotiation_type,
		WPML_Debug_BackTrace $debug_backtrace
	) {
		$this->term_element_factory      = $term_element_factory;
		$this->url_converter             = $url_converter;
		$this->cache_factory             = $cache_factory;
		$this->ls_languages_status       = $ls_language_status;
		$this->language_negotiation_type = (int) $language_negotiation_type;
		$this->debug_backtrace           = $debug_backtrace;
	}

	public function add_hooks() {
		add_filter( 'term_link', array( $this, 'cached_filter_tax_permalink' ), 1, 3 );
	}

	public function cached_filter_tax_permalink( $permalink, $tag, $taxonomy ) {
		if ( $this->is_canonical_redirect_context() ) {
			return remove_query_arg( 'lang', $permalink );
		}

		$tag    = is_object( $tag ) ? $tag : get_term( $tag, $taxonomy );
		$tag_id = $tag ? $tag->term_id : 0;

		$term_taxonomy_id = $tag_id && isset( $tag->term_taxonomy_id ) ? (int) $tag->term_taxonomy_id : 0;

		$cache = $this->cache_factory->create_language_aware_cache_item( self::CACHE_GROUP, array(
			$permalink,
			$tag_id,
			$taxonomy,
			$this->is_link_for_language_switcher(),
			$this->get_term_language( $term_taxonomy_id ),
		) );
		if ( $cache->exists() ) {
			return $cache->get();
		}

		$permalink = $this->filter_tax_permalink( $permalink, $term_taxonomy_id );

		$cache->set( $permalink );

		return $permalink;
	}

	private function get_term_language( $term_taxonomy_id ) {
		global $wpml_term_translations;

		if ( ! $term_taxonomy_id ) {
			return null;
		}

		if ( $wpml_term_translations instanceof WPML_Term_Translation ) {
			return $wpml_term_translations->get_element_lang_code( $term_taxonomy_id );
		}

		return $this->term_element_factory->create( $term_taxonomy_id, 'term' )->get_language_code();
	}

	private function filter_tax_permalink( $permalink, $term_taxonomy_id ) {
		if ( $term_taxonomy_id ) {
			$term_element = $this->term_element_factory->create( $term_taxonomy_id, 'term' );

			if ( ! $this->is_display_as_translated_and_in_default_lang( $term_element )
				|| $this->is_link_for_language_switcher()
			) {
				$term_language = $term_element->get_language_code();

				if ( (bool) $term_language ) {
					$permalink = $this->url_converter->convert_url( $permalink, $term_language );
				}
			}
		}

		return $permalink;
	}

	private function is_display_as_translated_and_in_default_lang( WPML_Translation_Element $element ) {
		return $element->is_display_as_translated()
			   && $element->is_in_default_language();
	}

	private function is_link_for_language_switcher() {
		return $this->ls_languages_status->is_getting_ls_languages();
	}

	private function is_canonical_redirect_context() {
		return WPML_LANGUAGE_NEGOTIATION_TYPE_PARAMETER === $this->language_negotiation_type
			&& doing_action( 'template_redirect' )
			&& $this->debug_backtrace->is_function_in_call_stack( 'redirect_canonical' );
	}


}
