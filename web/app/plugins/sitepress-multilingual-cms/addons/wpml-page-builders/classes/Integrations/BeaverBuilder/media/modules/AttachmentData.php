<?php

namespace WPML\PB\BeaverBuilder\Media\Modules;

class AttachmentData extends \WPML_Beaver_Builder_Media_Node {

	const URL_PROPERTIES = [ 'url', 'fallback' ];

	private $field_names;

	public function __construct( \IWPML_PB_Media_Find_And_Translate $media_translate, array $field_names ) {
		parent::__construct( $media_translate );
		$this->field_names = $field_names;
	}

	public function translate( $node_data, $target_lang, $source_lang ) {
		foreach ( $this->field_names as $field_name ) {
			if ( isset( $node_data->{$field_name} ) && is_object( $node_data->{$field_name} ) ) {
				$node_data->{$field_name} = $this->translate_urls( clone $node_data->{$field_name}, $target_lang, $source_lang );
			}
		}

		return $node_data;
	}

	private function translate_urls( $attachment_data, $target_lang, $source_lang ) {
		foreach ( self::URL_PROPERTIES as $property ) {
			if ( ! empty( $attachment_data->{$property} ) && is_string( $attachment_data->{$property} ) ) {
				$attachment_data->{$property} = $this->media_translate->translate_image_url( $attachment_data->{$property}, $target_lang, $source_lang );
			}
		}

		return $attachment_data;
	}
}
