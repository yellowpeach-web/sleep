<?php

namespace WPML\PB\BeaverBuilder\Hooks;

use FLBuilderModel;
use FLBuilderUIIFrame;
use WPML\FP\Obj;
use WPML\LIB\WP\Hooks;
use function WPML\FP\spreadArgs;

class Editor implements \IWPML_Frontend_Action {

	const LAYOUT_SAVE_ACTIONS = [ 'save_layout', 'save_draft' ];

	public function add_hooks() {
		Hooks::onFilter( 'wpml_pb_is_editing_translation_with_native_editor', 10, 2 )
			->then(
				spreadArgs(
					function( $isTranslationWithNativeEditor, $translatedPostId ) {
						return $isTranslationWithNativeEditor || $this->isLayoutSaveRequestOf( $translatedPostId );
					}
				)
			);

		Hooks::onAction( 'fl_builder_ui_enqueue_scripts' )
			->then( [ $this, 'maybeDisplayModalPageBuilderWarning' ] );

		if ( class_exists( FLBuilderUIIFrame::class ) ) {
			Hooks::onAction( 'wp_enqueue_scripts' )
				->then( [ $this, 'maybeDisplayModalPageBuilderWarningInLegacyUi' ] );
		}
	}

	public function maybeDisplayModalPageBuilderWarning() {
		if ( is_user_logged_in() && get_the_ID() ) {
			do_action( 'wpml_maybe_display_modal_page_builder_warning', get_the_ID(), 'Beaver Builder' );
		}
	}

	public function maybeDisplayModalPageBuilderWarningInLegacyUi() {
		if ( FLBuilderModel::is_builder_active() && ! $this->isIframeUiRequest() ) {
			$this->maybeDisplayModalPageBuilderWarning();
		}
	}

	private function isLayoutSaveRequestOf( $postId ) {
		$builderData = (array) Obj::prop( 'fl_builder_data', $_POST );

		return in_array( Obj::prop( 'action', $builderData ), self::LAYOUT_SAVE_ACTIONS, true )
			&& (int) Obj::prop( 'post_id', $builderData ) === $postId;
	}

	private function isIframeUiRequest() {
		return FLBuilderUIIFrame::is_ui_request() || FLBuilderUIIFrame::is_iframe_request();
	}
}
