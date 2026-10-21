<?php

class WPML_ACF_Pro implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const PRIORITY_ACF_COPIES_FIELDS        = 10;
	const PRIORITY_BEFORE_ACF_COPIES_FIELDS = self::PRIORITY_ACF_COPIES_FIELDS - 1;

	public function add_hooks() {
		add_action( 'icl_make_duplicate', [ $this, 'make_duplicate_action' ], self::PRIORITY_BEFORE_ACF_COPIES_FIELDS, 4 );

		add_filter( 'acf/duplicate_field/type=clone', [ $this, 'duplicate_clone_field' ], 10, 1 );
	}

	public function make_duplicate_action( $master_post_id, $lang, $post_array, $id ) {
		if ( ! apply_filters( 'wpml_is_operation', false, 'set_duplication' ) ) {
			return;
		}

		if ( isset( $post_array['post_type'] ) && 'acf-field-group' === $post_array['post_type'] ) {
			$this->delete_fields_of_field_group( $id );
		}
	}

	private function delete_fields_of_field_group( $field_group_id ) {
		$fields = get_posts(
			[
				'post_parent' => $field_group_id,
				'post_type'   => 'acf-field',
				'numberposts' => -1,
			]
		);

		foreach ( $fields as $field ) {
			acf_delete_field( $field->ID );
		}
	}

	public function duplicate_clone_field( $field ) {
		$parent_language = apply_filters( 'wpml_post_language_details', null, $field['parent'] );
		foreach ( $field['clone'] as $id => $key_post_name ) {
			if ( strpos( $key_post_name, 'group_' ) === 0 ) {
				$original_id   = $this->get_post_id_by_name( $key_post_name );
				$translated_id = apply_filters( 'wpml_object_id', $original_id, 'acf-field-group', false, $parent_language['language_code'] );
				if ( $translated_id ) {
					$post = get_post( $translated_id );
					if ( isset( $post->post_name ) ) {
						$field['clone'][ $id ] = $post->post_name;
					}
				}
			}
		}

		return $field;
	}

	private function get_post_id_by_name( $post_name, $output = OBJECT ) {
		global $wpdb;
		$post_id = null;
		$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_name = %s", $post_name ) );

		return $post_id;
	}

}
