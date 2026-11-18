<?php

namespace WPML\PB\BeaverBuilder\Media\Modules;

class AttachmentFields extends \WPML_Beaver_Builder_Media_Node {

	const RESPONSIVE_SUFFIXES = [ '', '_large', '_medium', '_responsive' ];
	const URL_SUFFIX          = '_src';

	private $field_names;

	public function __construct( \IWPML_PB_Media_Find_And_Translate $media_translate, array $field_names ) {
		parent::__construct( $media_translate );
		$this->field_names = $field_names;
	}

	public function translate( $node_data, $target_lang, $source_lang ) {
		foreach ( $this->field_names as $field_name ) {
			foreach ( self::RESPONSIVE_SUFFIXES as $suffix ) {
				$node_data = $this->translate_field( $node_data, $field_name . $suffix, $target_lang, $source_lang );
			}
		}

		return $node_data;
	}

	private function translate_field( $node_data, $field, $target_lang, $source_lang ) {
		if ( empty( $node_data->{$field} ) || ! is_numeric( $node_data->{$field} ) ) {
			return $node_data;
		}

		$translated_id = $this->media_translate->translate_id( (int) $node_data->{$field}, $target_lang );

		if ( (int) $translated_id !== (int) $node_data->{$field} ) {
			$node_data->{$field} = $translated_id;
			$url_field           = $field . self::URL_SUFFIX;

			if ( ! empty( $node_data->{$url_field} ) ) {
				$node_data->{$url_field} = $this->media_translate->translate_image_url( $node_data->{$url_field}, $target_lang, $source_lang );
			}
		}

		return $node_data;
	}
}
