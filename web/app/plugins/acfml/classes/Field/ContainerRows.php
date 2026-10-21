<?php

namespace ACFML\Field;

use ACFML\Helper\Fields;

class ContainerRows {

	private $containers = [];

	public function getContainerField( int $postId, string $metaKey ) {
		$cacheKey = $postId . ':' . $metaKey;
		if ( ! array_key_exists( $cacheKey, $this->containers ) ) {
			$fieldKey = get_post_meta( $postId, '_' . $metaKey, true );
			$field    = is_string( $fieldKey ) && 0 === strpos( $fieldKey, 'field_' )
				? acf_get_field( $fieldKey )
				: null;

			$this->containers[ $cacheKey ] = is_array( $field ) && Fields::isWrapper( $field ) ? $field : null;
		}

		return $this->containers[ $cacheKey ];
	}

	public function getRows( int $postId, string $metaKey ) {
		return get_post_meta( $postId, $metaKey, true );
	}
}
