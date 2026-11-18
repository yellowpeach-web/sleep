<?php

class WPML_Media_String_Images_Translation implements IWPML_Action {

	private $images_updater;
	private $string_factory;
	private $image_translator;

	public function __construct(
		WPML_Media_Translated_Images_Update $images_updater,
		WPML_ST_String_Factory $string_factory,
		WPML_Media_Image_Translate $image_translator
	) {
		$this->images_updater   = $images_updater;
		$this->string_factory   = $string_factory;
		$this->image_translator = $image_translator;
	}

	public function add_hooks() {
		add_filter( 'wpml_st_string_translation_before_save', array( $this, 'translate_images' ), PHP_INT_MAX, 3 );
	}

	public function translate_images( $translation_data, $target_language, $string_id ) {
		if ( ! empty( $translation_data['value'] ) ) {
			$value = $translation_data['value'];

			if ( $this->is_single_address( $value ) ) {
				$translation_data['value'] = $this->translate_file_address( $value, $target_language, $string_id );

				return $translation_data;
			}

			$original_string = $this->string_factory->find_by_id( $string_id );

			$translation_data['value'] = $this->images_updater->replace_images_with_translations(
				$value,
				$target_language,
				$original_string->get_language()
			);
		}

		return $translation_data;
	}

	private function is_single_address( $value ) {
		if ( ! is_string( $value ) ) {
			return false;
		}

		$address = trim( $value );

		return '' !== $address && 1 !== preg_match( '/[\s<]/', $address );
	}

	private function translate_file_address( $value, $target_language, $string_id ) {
		if ( ! preg_match( '/^(\s*)([^?#\s]+)(\S*?)(\s*)$/', $value, $parts ) ) {
			return $value;
		}

		list( , $before, $address, $suffix, $after ) = $parts;

		if ( ! $this->images_updater->isTranslatableMediaSrc( $address ) || ! $this->images_updater->isUploadsUrl( $address ) ) {
			return $value;
		}

		$original_string = $this->string_factory->find_by_id( $string_id );
		$source_language = $original_string->get_language();

		$translated = $this->image_translator->get_translated_image_by_url( $address, $source_language, $target_language );

		if ( ! is_string( $translated ) || '' === $translated ) {
			$translated = $this->get_replacement_of_a_removed_file( $address, $original_string, $source_language, $target_language );
		}

		if ( ! is_string( $translated ) || '' === $translated ) {
			return $value;
		}

		return $before . $this->images_updater->get_url_in_form_of( $address, $translated ) . $suffix . $after;
	}

	private function get_replacement_of_a_removed_file( $address, $original_string, $source_language, $target_language ) {
		if ( $this->image_translator->get_attachment_id_by_url( $address ) ) {
			return false;
		}

		$original = trim( (string) $original_string->get_value() );

		if (
			$original === $address
			|| ! $this->is_single_address( $original )
			|| ! $this->images_updater->isTranslatableMediaSrc( $original )
			|| ! $this->images_updater->isUploadsUrl( $original )
		) {
			return false;
		}

		$original = (string) preg_replace( '/[?#].*$/', '', $original );

		return $this->image_translator->get_translated_image_by_url( $original, $source_language, $target_language );
	}

}
