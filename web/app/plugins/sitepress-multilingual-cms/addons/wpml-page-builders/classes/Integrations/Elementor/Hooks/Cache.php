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
		Hooks::onAction( \WPML\PB\Shutdown\Hooks::ACTION_TRANSLATION_WRITTEN )
			->then( spreadArgs( [ $this, 'flushAll' ] ) );
		Hooks::onAction( 'transition_post_status', 10, 3 )
			->then( fn( $args ) => $this->flushElementsOnTemplateStatus( ...$args ) );
	}

	public function flushElementsOnTemplateStatus( $newStatus, $oldStatus, $post ) {
		$isPublishChange = ( 'publish' === $newStatus ) !== ( 'publish' === $oldStatus );

		if (
			! $isPublishChange
			|| self::TEMPLATES_POST_TYPE !== Obj::prop( 'post_type', $post )
			|| ! $this->isTranslation( $post )
		) {
			return;
		}

		delete_post_meta_by_key( self::ELEMENT_CACHE_KEY );
	}

	private function isTranslation( $post ) {
		$details = apply_filters(
			'wpml_element_language_details',
			null,
			[
				'element_id'   => $post->ID,
				'element_type' => self::TEMPLATES_POST_TYPE,
			]
		);

		return (bool) Obj::prop( 'source_language_code', $details );
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
		if ( self::TEMPLATES_POST_TYPE === Obj::prop( 'post_type', $post ) ) {
			self::deletePostCss( $postId );
		}
	}

	public static function deletePostCss( $postId ) {
		if ( ! class_exists( \Elementor\Core\Files\CSS\Post::class ) ) {
			return;
		}

		try {
			$cssFile = \WPML\Container\make( \Elementor\Core\Files\CSS\Post::class, [ ':post_id' => $postId ] );
			if ( method_exists( $cssFile, 'delete' ) ) {
				$cssFile->delete();
			}
		} catch ( \Exception $e ) {
			return;
		}
	}

	private function flushElement( $postId ) {
		delete_metadata( 'post', $postId, self::ELEMENT_CACHE_KEY );
	}
}
