<?php

namespace WPML\Compatibility\WPBakery\Hooks;

use WPML\LIB\WP\Hooks;

use function WPML\FP\spreadArgs;

class RawHtml implements \IWPML_Frontend_Action, \IWPML_Backend_Action, \IWPML_REST_Action {

	const WPBAKERY_CUSTOM_HTML_STRIP = 'wpb_remove_custom_html';

	const PRIORITY_BEFORE_WPBAKERY_STRIP = 9;

	public function add_hooks() {
		Hooks::onFilter( 'content_save_pre', self::PRIORITY_BEFORE_WPBAKERY_STRIP )
			->then( spreadArgs( [ $this, 'skipCustomHtmlStripOnAteDelivery' ] ) );
	}

	public function skipCustomHtmlStripOnAteDelivery( $content ) {
		if ( apply_filters( 'wpml_pb_grant_unfiltered_html', false ) ) {
			$this->detachWpbakeryCustomHtmlStrip();
		}

		return $content;
	}

	private function detachWpbakeryCustomHtmlStrip() {
		$stripPriority = has_filter( 'content_save_pre', self::WPBAKERY_CUSTOM_HTML_STRIP );

		if ( false !== $stripPriority ) {
			remove_filter( 'content_save_pre', self::WPBAKERY_CUSTOM_HTML_STRIP, $stripPriority );
		}
	}
}
