<?php

namespace WPML\PB\Elementor\Hooks;

use WPML\FP\Obj;
use WPML\LIB\WP\Hooks;
use function WPML\FP\spreadArgs;

class PreviewRedirect implements \IWPML_Frontend_Action {

	const PREVIEW_ARG = 'elementor-preview';

	public function add_hooks() {
		Hooks::onFilter( 'wpml_is_redirected', 10, 2 )
			->then( spreadArgs( [ $this, 'keepPreviewAddress' ] ) );
	}

	public function keepPreviewAddress( $redirect, $postId ) {
		if ( ! $redirect ) {
			return $redirect;
		}

		$previewId = Obj::prop( self::PREVIEW_ARG, $_GET );

		if ( ! is_numeric( $previewId ) || (int) $previewId <= 0 || (int) $previewId !== (int) $postId ) {
			return $redirect;
		}

		return current_user_can( 'edit_post', (int) $previewId ) ? false : $redirect;
	}
}
