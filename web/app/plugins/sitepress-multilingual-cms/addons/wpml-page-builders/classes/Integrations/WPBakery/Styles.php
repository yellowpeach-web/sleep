<?php

namespace WPML\Compatibility\WPBakery;

use WPML\Element\API\PostTranslations;
use WPML\FP\Maybe;
use WPML_PB_Last_Translation_Edit_Mode;
use function WPML\FP\partialRight;

class Styles implements \IWPML_Frontend_Action, \IWPML_Backend_Action {

	const META_CUSTOM_CSS = [
		'_wpb_shortcodes_custom_css',
		'_wpb_post_custom_css',
	];

	public function add_hooks() {
		add_action( 'save_post', [ $this, 'copyCssFromOriginal' ] );
		add_action( 'icl_make_duplicate', [ $this, 'copyCssToDuplicate' ], 10, 4 );
	}

	public function copyCssFromOriginal( $postId ) {
		$ifTranslation = function ( $originalPostId ) use ( $postId ) {
			return $originalPostId && (int) $originalPostId !== (int) $postId;
		};

		$ifUsingWpBakery = partialRight( 'get_post_meta', '_wpb_vc_js_status', true );

		$copyCss = function ( $originalPostId ) use ( $postId ) {
			$this->copyCss( $originalPostId, $postId, WPML_PB_Last_Translation_Edit_Mode::is_translation_editor( $postId ) );
		};

		Maybe::of( $postId )
			->map( PostTranslations::getOriginalId() )
			->filter( $ifTranslation )
			->filter( $ifUsingWpBakery )
			->map( $copyCss );
	}

	public function copyCssToDuplicate( $masterPostId, $lang, $postArray, $duplicateId ) {
		$this->copyCss( $masterPostId, $duplicateId, true );
	}

	private function copyCss( $originalPostId, $postId, $overwrite ) {
		wpml_collect( self::META_CUSTOM_CSS )->map(
			function ( $key ) use ( $originalPostId, $postId, $overwrite ) {
				if ( ! $overwrite && get_post_meta( $postId, $key, true ) ) {
					return;
				}

				$css = get_post_meta( $originalPostId, $key, true );
				if ( $css ) {
					update_post_meta( $postId, $key, $css );
				} elseif ( $overwrite ) {
					delete_post_meta( $postId, $key );
				}
			}
		);
	}
}
