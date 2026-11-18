<?php

namespace WPML\TM\ATE\TranslateEverything;

class AppearedOnSite implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	public function add_hooks() {
		add_filter( 'wpml_exclude_post_from_auto_translate', [ $this, 'excludeUntilShown' ], 10, 2 );
	}

	public function excludeUntilShown( $excluded, $postId ) {
		if ( $excluded ) {
			return $excluded;
		}

		$held = $this->heldPostTypes();
		if ( ! $held ) {
			return $excluded;
		}

		$postId   = (int) $postId;
		$postType = get_post_type( $postId );
		if ( ! $postType || ! in_array( $postType, $held, true ) || $this->originalOf( $postId ) !== $postId ) {
			return $excluded;
		}

		return false === apply_filters( 'wpml_appearance_has', null, 'post_' . $postType, $postId ) ? true : $excluded;
	}

	private function heldPostTypes() {
		$declared  = apply_filters( 'wpml_appearance_part_of_types', [] );
		$postTypes = [];
		foreach ( is_array( $declared ) ? $declared : [] as $type ) {
			if ( ! is_string( $type ) || ! preg_match( '/^post_(.+)$/', $type, $match ) || isset( $postTypes[ $match[1] ] ) ) {
				continue;
			}
			if ( in_array( apply_filters( 'wpml_appearance_detection_mode', 'off', $type ), [ 'admin', 'visitors' ], true ) ) {
				$postTypes[ $match[1] ] = $match[1];
			}
		}

		return array_values( $postTypes );
	}

	private function originalOf( $postId ) {
		global $wpml_post_translations;

		if ( $wpml_post_translations instanceof \WPML_Post_Translation ) {
			$original = $wpml_post_translations->get_original_element( $postId, true );
			if ( $original ) {
				return (int) $original;
			}
		}

		return $postId;
	}
}
