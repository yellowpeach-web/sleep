<?php

class WPML_Copy_Once_Custom_Field implements IWPML_Backend_Action, IWPML_Frontend_Action, IWPML_DIC_Action {

	private $sitepress;
	private $wpml_post_translation;

	public function __construct( SitePress $sitepress, WPML_Post_Translation $wpml_post_translation ) {
		$this->sitepress             = $sitepress;
		$this->wpml_post_translation = $wpml_post_translation;
	}

	public function add_hooks() {
		add_action( 'wpml_after_save_post', array( $this, 'copy' ), 10, 1 );
		add_action( 'wpml_pro_translation_completed', array( $this, 'copy' ), 10, 1 );
	}

	public function copy( $post_id ) {
		$source_element_id = $this->wpml_post_translation->get_original_element( $post_id );

		if ( $source_element_id ) {
			$this->copy_from( $source_element_id, $post_id );
		}
	}

	public function copy_from( $source_element_id, $post_id ) {
		$custom_fields_to_copy = $this->sitepress->get_custom_fields_translation_settings( WPML_COPY_ONCE_CUSTOM_FIELD );

		$source_meta           = get_post_meta( $source_element_id );
		$source_meta           = is_array( $source_meta ) ? $source_meta : [];
		$resolved              = wpml_resolve_custom_field_preferences( array_keys( $source_meta ) );
		$custom_fields_to_copy = array_merge( $custom_fields_to_copy, array_keys( $resolved, WPML_COPY_ONCE_CUSTOM_FIELD, true ) );

		$custom_fields_to_copy = \WPML\TM\Settings\MenuItemMetaBoundary::withoutSyncOwnedKeys( $custom_fields_to_copy );

		if ( empty( $custom_fields_to_copy ) ) {
			return;
		}

		$custom_fields = get_post_meta( $post_id );

		foreach ( $custom_fields_to_copy as $meta_key ) {
			if ( ! isset( $source_meta[ $meta_key ] ) ) {
				continue;
			}

			$values = isset( $custom_fields[ $meta_key ] )
					&& ! empty( $custom_fields[ $meta_key ] )
				? [ $custom_fields[ $meta_key ] ]
				: [];

			$values = apply_filters(
				'wpml_custom_field_values',
				$values,
				[
					'post_id'                   => $post_id,
					'meta_key'                  => $meta_key,
					'custom_fields_translation' => WPML_COPY_ONCE_CUSTOM_FIELD,
				]
			);

			if ( empty( $values ) ) {
				$this->sitepress->sync_custom_field( $source_element_id, $post_id, $meta_key );
			}
		}
	}
}
