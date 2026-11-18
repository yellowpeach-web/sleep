<?php

namespace WPML\PB\Elementor\Hooks;

use WPML\FP\Obj;
use WPML\FP\Relation;
use WPML\LIB\WP\Hooks;
use function WPML\FP\spreadArgs;

class Editor implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const COMPONENTS_PUBLISH_ROUTE = \WPML\PB\Elementor\V4\Component\QueryHooks::REST_ROUTE . '/status';

	private $componentsPublishedOverRest = [];

	public function add_hooks() {
		Hooks::onFilter( 'wpml_pb_is_editing_translation_with_native_editor', 10, 2 )
			->then(
				spreadArgs(
					function( $isTranslationWithNativeEditor, $translatedPostId ) {
						$isSavedWithElementorAjax = function () use ( $translatedPostId ) {
							return (int) Obj::prop( 'editor_post_id', $_POST ) === $translatedPostId
								&& Relation::propEq( 'action', 'elementor_ajax', $_POST );
						};

						return $isTranslationWithNativeEditor
							|| $isSavedWithElementorAjax()
							|| in_array( (int) $translatedPostId, $this->componentsPublishedOverRest, true );
					}
				)
			);

		Hooks::onFilter( 'rest_request_before_callbacks', 10, 3 )
			->then(
				function ( $args ) {
					return $this->rememberComponentsPublishedOverRest( ...$args );
				}
			);

		Hooks::onAction( 'elementor/editor/footer' )
			->then( [ $this, 'maybeDisplayModalPageBuilderWarning' ] );
	}

	public function rememberComponentsPublishedOverRest( $response, $handler, $request ) {
		if ( self::COMPONENTS_PUBLISH_ROUTE === $request->get_route() ) {
			$this->componentsPublishedOverRest = array_map( 'intval', (array) $request->get_param( 'ids' ) );
		}

		return $response;
	}

	public function maybeDisplayModalPageBuilderWarning() {
		$postId = (int) Obj::prop( 'post', $_GET );

		if ( is_user_logged_in() && $postId ) {
			do_action( 'wpml_maybe_display_modal_page_builder_warning', $postId, 'Elementor' );
		}
	}
}


