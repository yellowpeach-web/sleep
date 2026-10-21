<?php

class WPML_Page_Builders_Integration {

	const STRINGS_TRANSLATED_PRIORITY = 10;

	private $register_strings;

	private $update_translation;

	private $data_settings;

	public function __construct(
		WPML_Page_Builders_Register_Strings $register_strings,
		WPML_Page_Builders_Update_Translation $update_translation,
		IWPML_Page_Builders_Data_Settings $data_settings
	) {
		$this->register_strings = $register_strings;
		$this->update_translation = $update_translation;
		$this->data_settings = $data_settings;
	}

	public function add_hooks() {
		add_filter( 'wpml_page_builder_support_required', array( $this, 'support_required' ) );
		add_action( 'wpml_page_builder_register_strings', array( $this, 'register_pb_strings' ), 10, 2 );
		add_action( 'wpml_page_builder_string_translated', array( $this, 'update_translated_post' ), self::STRINGS_TRANSLATED_PRIORITY, 5 );
		add_filter( 'wpml_get_translatable_types', array( $this, 'remove_shortcode_strings_type_filter' ), 12, 1 );
		add_filter( 'wpml_pb_string_texts_in_post', array( $this, 'string_texts_in_post' ), 10, 2 );

		$this->data_settings->add_hooks();
	}

	public function string_texts_in_post( $texts, $post ) {
		if ( ! $post instanceof WP_Post ) {
			return $texts;
		}

		return (array) $texts + $this->register_strings->collect_strings( $post );
	}

	public function support_required( array $page_builder_plugins ) {
		$page_builder_plugins[] = $this->data_settings->get_pb_name();
		return $page_builder_plugins;
	}

	public function register_pb_strings( $post, $package_key ) {
		if ( $this->data_settings->get_pb_name() === $package_key['kind'] ) {
			$this->register_strings->register_strings( $post, $package_key );
		}
	}

	public function update_translated_post( $kind, $translated_post_id, WP_Post $original_post, $string_translations, $lang ) {
		if ( $this->data_settings->get_pb_name() === $kind ) {
			$this->update_translation->update( $translated_post_id, $original_post, $string_translations, $lang );
		}
	}

	public function remove_shortcode_strings_type_filter( $types ) {
		unset( $types[ sanitize_title_with_dashes( $this->data_settings->get_pb_name() ) ] );
		return $types;
	}

}