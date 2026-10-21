<?php

namespace WPML\PB\Elementor\V4\Component;

use WPML\LIB\WP\Hooks;
use function WPML\FP\spreadArgs;

class Cache implements \IWPML_Frontend_Action, \IWPML_Backend_Action {

	const LOCAL_STYLES_KEY = 'local';

	const RELATED_POSTS_KEY = 'component-styles-related-posts';

	public function add_hooks() {
		Hooks::onAction( 'wpml_pro_translation_completed' )
			->then( spreadArgs( [ $this, 'flush' ] ) );
	}

	public function flush( $postId ) {
		if ( QueryHooks::POST_TYPE !== get_post_type( $postId ) ) {
			return;
		}

		$postId = (int) $postId;

		do_action( 'elementor/atomic-widgets/styles/clear', [ self::LOCAL_STYLES_KEY, $postId ] );

		if ( class_exists( \Elementor\Modules\AtomicWidgets\Styles\CacheValidity\Cache_Validity::class ) ) {
			( new \Elementor\Modules\AtomicWidgets\Styles\CacheValidity\Cache_Validity() )
				->invalidate( [ self::RELATED_POSTS_KEY, $postId ] );
		}
	}
}
