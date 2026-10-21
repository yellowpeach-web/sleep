<?php

class WPML_Media_Custom_Field_Batch_Url_Translation_Factory implements IWPML_Backend_Action_Loader, IWPML_Deferred_Action_Loader {

	const EXACT_ENTRY_MODES = array(
		'WPML_TRANSLATE_CUSTOM_FIELD',
		'WPML_COPY_CUSTOM_FIELD',
		'WPML_COPY_ONCE_CUSTOM_FIELD',
		'WPML_IGNORE_CUSTOM_FIELD',
	);

	public function get_load_action() {
		return 'wp_loaded';
	}

	public function create() {
		global $wpdb, $sitepress;

		if ( WPML_Media_Custom_Field_Batch_Url_Translation::is_ajax_request() ) {

			$translatable_custom_fields = $this->get_translatable_custom_fields( $sitepress, $wpdb );

			$custom_field_images_translation_factory = new WPML_Media_Custom_Field_Images_Translation_Factory();

			return new WPML_Media_Custom_Field_Batch_Url_Translation(
				$custom_field_images_translation_factory->create(),
				$wpdb,
				$translatable_custom_fields
			);

		}

		return null;
	}

	private function get_translatable_custom_fields( $sitepress, $wpdb ) {
		$wp_api         = $sitepress->get_wp_api();
		$translate_mode = $wp_api->constant( 'WPML_TRANSLATE_CUSTOM_FIELD' );

		$exact_translatable = array();
		$exact_entry_keys   = array();
		foreach ( self::EXACT_ENTRY_MODES as $mode_name ) {
			$names = array_map(
				'strval',
				(array) $sitepress->get_custom_fields_translation_settings( $wp_api->constant( $mode_name ) )
			);

			$exact_entry_keys = array_merge( $exact_entry_keys, $names );
			if ( 'WPML_TRANSLATE_CUSTOM_FIELD' === $mode_name ) {
				$exact_translatable = $names;
			}
		}

		$rule_translatable = $this->get_rule_translatable_custom_fields(
			$wpdb,
			$exact_entry_keys,
			$translate_mode
		);

		return array_values( array_unique( array_merge( $exact_translatable, $rule_translatable ) ) );
	}

	private function get_rule_translatable_custom_fields( $wpdb, array $exact_entry_keys, $translate_mode ) {
		if ( ! function_exists( 'wpml_resolve_custom_field_preferences' ) ) {
			return array();
		}

		$candidate_keys = array_values(
			array_diff(
				array_map(
					'strval',
					(array) $wpdb->get_col( "SELECT DISTINCT meta_key FROM {$wpdb->postmeta}" )
				),
				$exact_entry_keys
			)
		);

		if ( ! $candidate_keys ) {
			return array();
		}

		$translatable = array();
		foreach ( wpml_resolve_custom_field_preferences( $candidate_keys, 'post' ) as $meta_key => $mode ) {
			if ( (int) $mode === (int) $translate_mode ) {
				$translatable[] = (string) $meta_key;
			}
		}

		return $translatable;
	}

}
