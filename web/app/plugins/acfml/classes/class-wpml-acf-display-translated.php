<?php

class WPML_ACF_Display_Translated implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {
	public function add_hooks() {
		add_filter( 'acf/fields/relationship/query', [ $this, 'allow_query_display_translated_in_wp_admin' ] );
		add_filter( 'acf/fields/post_object/query', [ $this, 'allow_query_display_translated_in_wp_admin' ] );
	}

	public function allow_query_display_translated_in_wp_admin( $args ) {
		if ( ! has_filter( 'wpml_should_use_display_as_translated_snippet', [ $this, 'query_should_use_display_as_translated' ] ) ) {
			add_filter( 'wpml_should_use_display_as_translated_snippet', [ $this, 'query_should_use_display_as_translated' ], 10, 2 );
		}

		return $args;
	}

	public function query_should_use_display_as_translated( $use_snippet, $post_types ) {
		if ( wpml_is_ajax() ) {
			$use_snippet = true;
		}

		return $use_snippet;
	}
}
