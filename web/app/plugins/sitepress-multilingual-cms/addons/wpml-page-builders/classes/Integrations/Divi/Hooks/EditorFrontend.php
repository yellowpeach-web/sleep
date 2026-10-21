<?php

namespace WPML\Compatibility\Divi\Hooks;

use WPML\FP\Fns;
use WPML\LIB\WP\Hooks;
use WPML\PB\Integrations\Divi\Helper;

class EditorFrontend implements \IWPML_Frontend_Action {

	const IFRAME_SELECTOR_DIVI_4 = 'html.et-fb-app-frame';

	const IFRAME_SELECTOR_DIVI_5 = 'html.et-vb-app-ancestor';

	public function add_hooks() {
		if ( function_exists( 'et_core_is_fb_enabled' ) ) {
			Hooks::onAction( 'et_builder_ready' )
			     ->then( Fns::tap( [ $this, 'maybeDisplayModalPageBuilderWarning' ] ) );
		}
	}

	public function maybeDisplayModalPageBuilderWarning() {
		$postId = get_the_ID();

		if ( is_user_logged_in() && function_exists( 'et_core_is_fb_enabled' ) && et_core_is_fb_enabled() && $postId ) {
			$diviArgs = [
				'iframeModeQuerySelector' => $this->iframeSelector( $postId ),
			];
			do_action( 'wpml_maybe_display_modal_page_builder_warning', $postId, 'Divi Builder', $diviArgs );
		}
	}

	private function iframeSelector( $postId ) {
		return Helper::isPostUsingDivi5( $postId ) ? self::IFRAME_SELECTOR_DIVI_5 : self::IFRAME_SELECTOR_DIVI_4;
	}
}
