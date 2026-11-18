<?php

namespace WPML\Compatibility\WPBakery;

use WPML\Element\API\Post;
use WPML\Element\API\PostTranslations;
use WPML\FP\Maybe;
use WPML\PB\Media\CssImages;
use WPML_PB_Last_Translation_Edit_Mode;
use function WPML\FP\partialRight;

class Styles implements \IWPML_Frontend_Action, \IWPML_Backend_Action {

	const META_DESIGN_OPTIONS_CSS = '_wpb_shortcodes_custom_css';

	const META_CUSTOM_CSS = [
		self::META_DESIGN_OPTIONS_CSS,
		'_wpb_post_custom_css',
	];

	const META_CUSTOM_JS = [
		'_wpb_post_custom_js_header',
		'_wpb_post_custom_js_footer',
	];

	const META_EDITOR = [
		'_wpb_vc_js_status',
		'_wpb_vc_editor_type',
		'_wpb_vc_use_optimized_css',
	];

	private $cssImages;

	public function __construct( ?CssImages $cssImages = null ) {
		$this->cssImages = $cssImages;
	}

	public function add_hooks() {
		add_action( 'save_post', [ $this, 'copyFromOriginal' ] );
		add_action( 'icl_make_duplicate', [ $this, 'copyToDuplicate' ], 10, 4 );
	}

	public function copyFromOriginal( $postId ) {
		$ifTranslation = function ( $originalPostId ) use ( $postId ) {
			return $originalPostId && (int) $originalPostId !== (int) $postId;
		};

		$ifUsingWpBakery = partialRight( 'get_post_meta', '_wpb_vc_js_status', true );

		$copyMeta = function ( $originalPostId ) use ( $postId ) {
			$this->copyMeta(
				$originalPostId,
				$postId,
				WPML_PB_Last_Translation_Edit_Mode::is_translation_editor( $postId ),
				$this->cssImages ? Post::getLang( $postId ) : null
			);
		};

		Maybe::of( $postId )
			->map( PostTranslations::getOriginalId() )
			->filter( $ifTranslation )
			->filter( $ifUsingWpBakery )
			->map( $copyMeta );
	}

	public function copyToDuplicate( $masterPostId, $lang, $postArray, $duplicateId ) {
		$this->copyMeta( $masterPostId, $duplicateId, true, $lang );
	}

	private function copyMeta( $originalPostId, $postId, $overwrite, $targetLanguage ) {
		$shouldTranslateImages = $targetLanguage && $this->cssImages;
		$sourceLanguage        = $shouldTranslateImages ? (string) Post::getLang( $originalPostId ) : '';

		wpml_collect( array_merge( self::META_CUSTOM_CSS, self::META_CUSTOM_JS, self::META_EDITOR ) )->map(
			function ( $key ) use ( $originalPostId, $postId, $overwrite, $targetLanguage, $shouldTranslateImages, $sourceLanguage ) {
				if ( ! $overwrite && get_post_meta( $postId, $key, true ) ) {
					return;
				}

				$value = get_post_meta( $originalPostId, $key, true );
				if ( $shouldTranslateImages && self::META_DESIGN_OPTIONS_CSS === $key && is_string( $value ) && $value ) {
					$value = $this->cssImages->translate( $value, $targetLanguage, $sourceLanguage );
				}

				if ( $value ) {
					update_post_meta( $postId, $key, $value );
				} elseif ( $overwrite ) {
					delete_post_meta( $postId, $key );
				}
			}
		);
	}
}
