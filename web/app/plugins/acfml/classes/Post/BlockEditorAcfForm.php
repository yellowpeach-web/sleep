<?php

namespace ACFML\Post;

class BlockEditorAcfForm {

	private $hasForm = [];

	public function isSentFromEditScreen(): bool {
		$path = (string) wp_parse_url( (string) wp_get_raw_referer(), PHP_URL_PATH );

		return (bool) preg_match( '#/wp-admin/post(-new)?\.php$#', $path );
	}

	public function ownsField( int $postId, string $metaKey ): bool {
		$originalId = (int) apply_filters( 'wpml_original_element_id', null, $postId, 'post_' . get_post_type( $postId ) );

		foreach ( array_unique( array_filter( [ $postId, $originalId ] ) ) as $id ) {
			$fieldKey = get_post_meta( $id, '_' . $metaKey, true );
			if ( is_string( $fieldKey ) && acf_is_field_key( $fieldKey ) ) {
				return true;
			}
		}

		return false;
	}

	public function isShownOn( int $postId ): bool {
		if ( ! isset( $this->hasForm[ $postId ] ) ) {
			$this->hasForm[ $postId ] = (bool) acf_get_field_groups( [ 'post_id' => $postId ] );
		}

		return $this->hasForm[ $postId ];
	}
}
