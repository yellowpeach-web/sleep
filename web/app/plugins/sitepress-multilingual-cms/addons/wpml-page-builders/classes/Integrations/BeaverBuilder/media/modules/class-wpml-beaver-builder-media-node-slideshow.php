<?php

class WPML_Beaver_Builder_Media_Node_Slideshow extends WPML_Beaver_Builder_Media_Node {

	private $url_properties = array(
		'largeURL',
		'x3largeURL',
		'thumbURL',
	);

	private $photos_field;

	private $photo_data_field;

	public function __construct( IWPML_PB_Media_Find_And_Translate $media_translate, $photos_field = 'photos', $photo_data_field = 'photo_data' ) {
		parent::__construct( $media_translate );
		$this->photos_field     = $photos_field;
		$this->photo_data_field = $photo_data_field;
	}

	public function translate( $node_data, $target_lang, $source_lang ) {
		$photos_field     = $this->photos_field;
		$photo_data_field = $this->photo_data_field;

		if ( ! isset( $node_data->{$photos_field}, $node_data->{$photo_data_field} ) || ! is_array( $node_data->{$photos_field} ) ) {
			return $node_data;
		}

		foreach ( $node_data->{$photos_field} as &$photo ) {
			$photo = $this->media_translate->translate_id( $photo, $target_lang );
		}
		unset( $photo );

		$translated_photo_data = [];

		foreach ( $node_data->{$photo_data_field} as $photo_id => $photo_data ) {
			$translated_id = $this->media_translate->translate_id( $photo_id, $target_lang );

			if ( $translated_id !== $photo_id && is_object( $photo_data ) ) {
				$photo_data = $this->translate_photo_data( $photo_data, $translated_id, $target_lang, $source_lang );
			}

			$translated_photo_data[ $translated_id ] = $photo_data;
		}

		$node_data->{$photo_data_field} = is_object( $node_data->{$photo_data_field} ) ? (object) $translated_photo_data : $translated_photo_data;

		return $node_data;
	}

	private function translate_photo_data( $photo_data, $translated_id, $target_lang, $source_lang ) {
		$translation_data    = wp_prepare_attachment_for_js( $translated_id );
		$photo_data->caption = $translation_data['caption'];

		foreach ( $this->url_properties as $property ) {
			if ( isset( $photo_data->{$property} ) && $photo_data->{$property} ) {
				$photo_data->{$property} = $this->media_translate->translate_image_url( $photo_data->{$property}, $target_lang, $source_lang );
			}
		}

		return $photo_data;
	}
}
