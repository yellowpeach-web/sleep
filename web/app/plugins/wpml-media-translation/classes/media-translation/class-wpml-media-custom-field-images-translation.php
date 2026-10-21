<?php

use WPML\Media\Classes\WPML_Media_Element_Translation_Factory;

class WPML_Media_Custom_Field_Images_Translation implements IWPML_Action {

	private $images_updater;
	private $sitepress;
	private $iclTranslationManagement;
	private $missing_translation_creator;

	public function __construct(
		WPML_Media_Translated_Images_Update $images_updater,
		SitePress $sitepress,
		TranslationManagement $iclTranslationManagement,
		WPML_Media_Missing_Translation_Creator $missing_translation_creator
	) {
		$this->images_updater              = $images_updater;
		$this->sitepress                   = $sitepress;
		$this->iclTranslationManagement    = $iclTranslationManagement;
		$this->missing_translation_creator = $missing_translation_creator;
	}

	public function add_hooks() {
		add_action( 'updated_post_meta', array( $this, 'translate_images' ), PHP_INT_MAX, 4 );
		add_action( 'added_post_meta', array( $this, 'translate_images' ), PHP_INT_MAX, 4 );
	}

	public function translate_images( $meta_id, $object_id, $meta_key, $meta_value ) {

		$settings_factory = new WPML_Custom_Field_Setting_Factory( $this->iclTranslationManagement );
		$setting          = $settings_factory->post_meta_setting( $meta_key );

		$is_custom_field_translatable = $this->sitepress->get_wp_api()
														->constant( 'WPML_TRANSLATE_CUSTOM_FIELD' ) === $setting->status();
		$post_type                    = get_post_type( $object_id );
		$is_post_translatable         = $this->sitepress->is_translated_post_type( $post_type );

		if ( is_string( $meta_value ) && $is_post_translatable && $is_custom_field_translatable ) {
			$post_element    = WPML_Media_Element_Translation_Factory::create( $object_id );
			$source_language = $post_element->get_source_language_code();
			if ( null !== $source_language ) {
				$this->create_missing_media_translations(
					$meta_value,
					$post_element->get_language_code(),
					$source_language
				);

				$this->filter_meta_value_and_update(
					$meta_value,
					$meta_key,
					$post_element->get_language_code(),
					$source_language,
					$object_id
				);
			} else {
				foreach ( array_keys( $this->sitepress->get_active_languages() ) as $language ) {
					$translation = $post_element->get_translation( $language );
					if ( $translation ) {
						$this->filter_meta_value_and_update(
							$meta_value,
							$meta_key,
							$language,
							$source_language,
							$translation->get_id()
						);
					}
				}
			}
		}
	}

	private function create_missing_media_translations( $meta_value, $target_language, $source_language ) {
		$this->missing_translation_creator->create_for_text( $meta_value, $target_language, $source_language );
	}

	private function filter_meta_value_and_update( $meta_value, $meta_key, $target_language, $source_language, $post_id ) {
		$meta_value_filtered = $this->images_updater->replace_images_with_translations(
			$meta_value,
			$target_language,
			$source_language
		);

		if ( $meta_value_filtered !== $meta_value ) {
			remove_action( 'updated_post_meta', array( $this, 'translate_images' ), PHP_INT_MAX );
			update_post_meta( $post_id, $meta_key, wp_slash( $meta_value_filtered ), $meta_value );
			add_action( 'updated_post_meta', array( $this, 'translate_images' ), PHP_INT_MAX, 4 );
		}

		return $meta_value_filtered;
	}
}
