<?php

namespace WPML\Compatibility\WPBakery\Hooks;

use WPML\FP\Obj;
use WPML\FP\Relation;
use WPML\LIB\WP\Hooks;
use function WPML\FP\spreadArgs;

class Editor implements \IWPML_Frontend_Action, \IWPML_Backend_Action {
	public function add_hooks() {
		Hooks::onFilter( 'wpml_pb_is_editing_translation_with_native_editor', 10, 2 )
			->then(
				spreadArgs(
					function ( $isTranslationWithNativeEditor, $translatedPostId ) {
						return $isTranslationWithNativeEditor || $this->isFrontendEditorSaving( $translatedPostId );
					}
				)
			);

		Hooks::onAction( 'vc_frontend_editor_render' )
			->then( [ $this, 'displayModalPageBuilderWarning' ] );
	}

	private function isFrontendEditorSaving( int $translatedPostId ): bool {
		return Relation::propEq( 'action', 'vc_save', $_POST ) && (int) Obj::prop( 'post_id', $_POST ) === $translatedPostId;
	}

	public function displayModalPageBuilderWarning() {
		if ( is_user_logged_in() && isset( $_GET['post_id'] ) ) {
			do_action( 'wpml_maybe_display_modal_page_builder_warning', (int) $_GET['post_id'], 'WPBakery' );
		}
	}
}
