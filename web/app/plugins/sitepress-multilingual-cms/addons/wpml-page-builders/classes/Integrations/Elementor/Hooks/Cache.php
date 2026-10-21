<?php

namespace WPML\PB\Elementor\Hooks;

use WPML\FP\Obj;
use WPML\LIB\WP\Hooks;
use function WPML\FP\spreadArgs;

class Cache implements \IWPML_Frontend_Action, \IWPML_Backend_Action {

	const TEMPLATES_POST_TYPE = 'elementor_library';
	const ELEMENT_CACHE_KEY   = '_elementor_element_cache';

	public function add_hooks() {
		Hooks::onAction( 'wpml_translation_job_saved' )
			->then( spreadArgs( [ $this, 'flushAll' ] ) );
		Hooks::onAction( 'wpml_pb_translations_auto_updated' )
			->then( spreadArgs( [ $this, 'flushAll' ] ) );
		Hooks::onAction( 'wpml_pb_duplicate_links_converted' )
			->then( spreadArgs( [ $this, 'flushAll' ] ) );
		Hooks::onAction( 'wpml_pb_resave_post_translation' )
			->then( spreadArgs( [ $this, 'flushForElement' ] ) );
	}

	public function flushForElement( $postElement ) {
		if ( $postElement instanceof \WPML_Post_Element ) {
			$this->flushAll( $postElement->get_id() );
		}
	}

	public function flushAll( $postId ) {
		$this->flushCss( $postId );
		$this->flushElement( $postId );
	}

	private function flushCss( $postId ) {
		$post = get_post( $postId );
		if (
			self::TEMPLATES_POST_TYPE === Obj::prop( 'post_type', $post )
			&& class_exists( \Elementor\Core\Files\CSS\Post::class )
		) {
			try {
				$cssFile = \WPML\Container\make( \Elementor\Core\Files\CSS\Post::class, [ ':post_id' => $postId ] );
				if ( method_exists( $cssFile, 'delete' ) ) {
					$cssFile->delete();
				}
			} catch ( \Exception $e ) {
			}
		}
	}

	private function flushElement( $postId ) {
		delete_metadata( 'post', $postId, self::ELEMENT_CACHE_KEY );
	}
}
