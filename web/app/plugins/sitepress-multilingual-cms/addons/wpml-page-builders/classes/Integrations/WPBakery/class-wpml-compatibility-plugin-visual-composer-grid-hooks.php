<?php

class WPML_Compatibility_Plugin_Visual_Composer_Grid_Hooks implements IWPML_Action {

	private $current_language;

	private $element_factory;

	public function __construct(
		IWPML_Current_Language $current_language,
		WPML_Translation_Element_Factory $element_factory
	) {
		$this->current_language = $current_language;
		$this->element_factory = $element_factory;
	}

	public function add_hooks() {
		add_filter( 'wpml_pb_shortcode_decode', array( $this, 'vc_grid_link_decode' ), 10, 3 );
		add_filter( 'vc_shortcode_content_filter', array( $this, 'vc_shortcode_content_filter' ) );
		add_filter( 'vc_basic_grid_find_post_shortcode', array( $this, 'find_grid_shortcode_in_source_page' ), 10, 3 );
	}

	function vc_grid_link_decode( $string, $encoding, $encoded_string ) {
		if ( 'vc_link' === $encoding && empty( $string ) ) {
			return $encoded_string;
		}

		return $string;
	}

	public function vc_shortcode_content_filter( $content ) {
		$pattern = '/(\[(?:vc_basic_grid|vc_masonry_grid|vc_media_grid|vc_masonry_media_grid)(?=\s)[^\]]*?\sitem=")([^"]*)(")/';
		return preg_replace_callback( $pattern, array( $this, 'replace_grid_id' ), $content );
	}

	private function replace_grid_id( array $matches ) {
		$grid_id = (int) $matches[2];

		if ( $grid_id > 0 ) {
			$before = $matches[1];
			$after  = $matches[3];

			return $before . $this->translate_post_id( $grid_id, $this->current_language->get_current_language() ) . $after;
		}

		return $matches[0];
	}

	public function find_grid_shortcode_in_source_page( $shortcode, $page_id, $grid_id ) {
		if ( is_array( $shortcode ) || ! is_numeric( $page_id ) || (int) $page_id <= 0 || ! is_string( $grid_id ) ) {
			return $shortcode;
		}

		$page        = $this->element_factory->create( (int) $page_id, 'post' );
		$source_page = $page->get_source_element();

		if ( ! $source_page ) {
			return $shortcode;
		}

		$source_shortcode = $this->get_saved_grid_shortcode( $source_page->get_element_id(), $grid_id );

		if ( ! $source_shortcode ) {
			return $shortcode;
		}

		$source_shortcode['atts'] = $this->translate_grid_attributes( $source_shortcode['atts'], $page->get_language_code() );

		return $source_shortcode;
	}

	private function get_saved_grid_shortcode( int $page_id, string $grid_id ) {
		foreach ( (array) get_post_meta( $page_id, '_vc_post_settings' ) as $settings ) {
			if ( isset( $settings['vc_grid_id']['shortcodes'][ $grid_id ]['atts'] ) && is_array( $settings['vc_grid_id']['shortcodes'][ $grid_id ]['atts'] ) ) {
				return $settings['vc_grid_id']['shortcodes'][ $grid_id ];
			}
		}

		return null;
	}

	private function translate_grid_attributes( array $attributes, string $language_code ): array {
		$translate_post_id = function ( $post_id ) use ( $language_code ) {
			return $this->translate_post_id( $post_id, $language_code );
		};
		$translate_term_id = function ( $term_id ) use ( $language_code ) {
			return $this->translate_term_id( $term_id, $language_code );
		};

		if ( isset( $attributes['item'] ) && is_numeric( $attributes['item'] ) && (int) $attributes['item'] > 0 ) {
			$attributes['item'] = $translate_post_id( (int) $attributes['item'] );
		}

		foreach ( array( 'include', 'exclude' ) as $post_list_attribute ) {
			if ( ! empty( $attributes[ $post_list_attribute ] ) ) {
				$attributes[ $post_list_attribute ] = $this->translate_id_list( $attributes[ $post_list_attribute ], $translate_post_id );
			}
		}

		foreach ( array( 'taxonomies', 'exclude_filter' ) as $term_list_attribute ) {
			if ( ! empty( $attributes[ $term_list_attribute ] ) ) {
				$attributes[ $term_list_attribute ] = $this->translate_id_list( $attributes[ $term_list_attribute ], $translate_term_id );
			}
		}

		return $attributes;
	}

	private function translate_id_list( string $id_list, callable $translate_id ): string {
		$ids = array_filter(
			array_map( 'intval', explode( ',', $id_list ) ),
			function ( $id ) {
				return $id > 0;
			}
		);

		return implode( ',', array_map( $translate_id, $ids ) );
	}

	private function translate_post_id( int $post_id, string $language_code ): int {
		$translation = $this->element_factory->create( $post_id, 'post' )->get_translation( $language_code );

		return $translation ? (int) $translation->get_element_id() : $post_id;
	}

	private function translate_term_id( int $term_id, string $language_code ): int {
		$term = get_term( $term_id );

		if ( ! $term instanceof WP_Term ) {
			return $term_id;
		}

		return (int) apply_filters( 'wpml_object_id', $term_id, $term->taxonomy, true, $language_code );
	}
}
