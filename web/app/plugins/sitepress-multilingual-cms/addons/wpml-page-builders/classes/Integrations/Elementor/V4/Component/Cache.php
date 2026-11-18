<?php

namespace WPML\PB\Elementor\V4\Component;

use WPML\LIB\WP\Hooks;
use WPML\PB\Elementor\Hooks\SiteSettings;
use WPML\PB\Elementor\Hooks\Templates;
use function WPML\FP\spreadArgs;

class Cache implements \IWPML_Frontend_Action, \IWPML_Backend_Action {

	const LOCAL_STYLES_KEY = 'local';

	const RELATED_POSTS_KEY = 'component-styles-related-posts';

	const RELATED_TEMPLATES_KEY = 'template-styles-related-posts';

	public function add_hooks() {
		Hooks::onAction( 'wpml_pro_translation_completed' )
			->then( spreadArgs( [ $this, 'flush' ] ) );

		Hooks::onAction( 'wpml_page_builder_string_translated', \WPML_Page_Builders_Integration::STRINGS_TRANSLATED_PRIORITY + 1, 2 )
			->then( spreadArgs( [ $this, 'flushStringTranslated' ] ) );

		Hooks::onAction( 'icl_make_duplicate', 10, 4 )
			->then( spreadArgs( [ $this, 'flushDuplicate' ] ) );

		add_action( 'wpml_pb_after_page_without_elements_post_content_copy', [ $this, 'flush' ], \WPML_PB_Handle_Custom_Fields::PRIORITY + 1 );
	}

	public function flushStringTranslated( $kind, $translatedPostId ) {
		$this->flush( $translatedPostId );
	}

	public function flushDuplicate( $originalPostId, $lang, $postArray, $duplicateId ) {
		$this->flush( $duplicateId );

		if ( ! self::hasOwnStyles( $duplicateId ) ) {
			$this->flushUsedComponents( $duplicateId );
		}
	}

	public function flush( $postId ) {
		$hasOwnStyles = self::hasOwnStyles( $postId );

		$isElementorPost = ! $hasOwnStyles
			&& Templates::POST_TYPE !== get_post_type( $postId )
			&& \WPML_Elementor_Data_Settings::is_edited_with_elementor( (int) $postId );

		if ( ! $hasOwnStyles && ! $isElementorPost ) {
			return;
		}

		$postId = (int) $postId;

		do_action( 'elementor/atomic-widgets/styles/clear', [ self::LOCAL_STYLES_KEY, $postId ] );

		if ( $hasOwnStyles ) {
			$this->flushUsedComponents( $postId );
		}
	}

	public function flushUsedComponents( $postId ) {
		if ( class_exists( \Elementor\Modules\AtomicWidgets\Styles\CacheValidity\Cache_Validity::class ) ) {
			$cacheValidity = new \Elementor\Modules\AtomicWidgets\Styles\CacheValidity\Cache_Validity();

			foreach ( [ self::RELATED_POSTS_KEY, self::RELATED_TEMPLATES_KEY ] as $key ) {
				$cacheValidity->invalidate( [ $key, (int) $postId ] );
			}
		}
	}

	public static function hasOwnStyles( $postId ) {
		$postType = get_post_type( $postId );

		if ( Templates::POST_TYPE === $postType ) {
			return 'kit' !== get_post_meta( (int) $postId, SiteSettings::TEMPLATE_TYPE_META, true );
		}

		return QueryHooks::POST_TYPE === $postType;
	}
}
