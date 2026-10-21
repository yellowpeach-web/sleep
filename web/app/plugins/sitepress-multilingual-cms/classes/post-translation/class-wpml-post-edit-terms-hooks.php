<?php

class WPML_Post_Edit_Terms_Hooks implements IWPML_Action {

	const AFTER_POST_DATA_SANITIZED_ACTION = 'init';

	private $language;

	private $wpdb;

	public function __construct( IWPML_Current_Language $current_language, wpdb $wpdb ) {
		$this->language = $current_language;
		$this->wpdb     = $wpdb;
	}

	public function add_hooks() {
		add_action( self::AFTER_POST_DATA_SANITIZED_ACTION, array( $this, 'set_tags_input_with_ids' ) );
	}

	public function set_tags_input_with_ids() {
		$tag_names = $this->get_tags_from_tax_input();

		if ( $tag_names ) {
			$wpdb = $this->wpdb;
			$tags = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT t.name, t.term_id FROM {$wpdb->terms} AS t
					LEFT JOIN {$wpdb->term_taxonomy} AS tt
						ON tt.term_id = t.term_id
					LEFT JOIN {$wpdb->prefix}icl_translations AS tr
						ON tr.element_id = tt.term_taxonomy_id AND tr.element_type = 'tax_post_tag'
					WHERE tr.language_code = %s AND t.name IN(" . implode( ', ', array_fill( 0, count( $tag_names ), '%s' ) ) . ')',
					array_merge( array( $this->language->get_current_language() ), $tag_names )
				)
			);

			foreach ( $tags as $tag ) {
				$_POST['tags_input'][] = (int) $tag->term_id;
			}
		}
	}

	public function get_tags_from_tax_input() {
		if ( ! empty( $_POST['tax_input']['post_tag'] ) ) {
			$tags = $_POST['tax_input']['post_tag'];

			if ( ! is_array( $tags ) ) {
				$delimiter = _x( ',', 'tag delimiter' );
				$tags      = explode( $delimiter, trim( $tags, " \n\t\r\0\x0B," ) );
			}

			if ( $tags ) {
				return array_map( 'trim', $tags );
			}
		}

		return null;
	}
}
