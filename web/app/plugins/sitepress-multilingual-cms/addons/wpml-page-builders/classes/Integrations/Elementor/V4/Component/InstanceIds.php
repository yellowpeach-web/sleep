<?php

namespace WPML\PB\Elementor\V4\Component;

use WPML\FP\Obj;
use WPML\PB\Elementor\Helper\ElementTree;
use WPML\PB\Elementor\V4\Hooks;

class InstanceIds {

	public function convert( array $data, $lang ) {
		$path = array_merge( [ 'settings' ], Hooks::COMPONENT_ID_PATH );

		return ElementTree::map(
			$data,
			function ( array $element ) use ( $path, $lang ) {
				return $this->translateIdInPath( $element, $path, $lang );
			}
		);
	}

	public function convertForPost( array $data, $postId ) {
		$lang = apply_filters(
			'wpml_element_language_code',
			null,
			[
				'element_id'   => $postId,
				'element_type' => 'post_' . get_post_type( $postId ),
			]
		);

		return $lang ? $this->convert( $data, $lang ) : $data;
	}

	public function translateIdInPath( array $data, array $path, $lang = null ) {
		$postId = Obj::path( $path, $data );

		if ( ! is_numeric( $postId ) ) {
			return $data;
		}

		$translatedId = $this->translateId( (int) $postId, $lang );

		if ( $translatedId ) {
			$data = Obj::assocPath( $path, $translatedId, $data );
		}

		return $data;
	}

	public function translateId( $postId, $lang = null ) {
		$postType = get_post_type( $postId );
		if ( ! $postType ) {
			return null;
		}

		$translatedId = apply_filters( 'wpml_object_id', $postId, $postType, false, $lang );

		return $translatedId ? (int) $translatedId : null;
	}
}
