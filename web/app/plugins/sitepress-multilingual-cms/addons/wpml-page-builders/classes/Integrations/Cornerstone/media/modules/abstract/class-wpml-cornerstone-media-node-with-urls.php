<?php

abstract class WPML_Cornerstone_Media_Node_With_URLs extends WPML_Cornerstone_Media_Node {

	abstract protected function get_keys();

	public function translate( $node_data, $target_lang, $source_lang ) {
		foreach ( $this->get_keys() as $key ) {
			if ( ! empty( $node_data[ $key ] ) && is_string( $node_data[ $key ] ) ) {
				$node_data[ $key ] = $this->translateValue( $node_data[ $key ], $target_lang, $source_lang );
			}
		}

		return $node_data;
	}

	protected function translateValue( $value, $targetLanguage, $sourceLanguage ) {
		list( $attachmentId, $size ) = array_pad( explode( ':', $value, 2 ), 2, '' );

		if ( is_numeric( $attachmentId ) ) {
			return $this->media_translate->translate_id( (int) $attachmentId, $targetLanguage ) . ( '' === $size ? '' : ':' . $size );
		}

		return $this->media_translate->translate_image_url( $value, $targetLanguage, $sourceLanguage );
	}
}
