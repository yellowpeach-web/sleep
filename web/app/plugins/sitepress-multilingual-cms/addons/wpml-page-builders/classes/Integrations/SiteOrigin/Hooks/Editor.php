<?php

namespace WPML\PB\SiteOrigin\Hooks;

use WPML\FP\Obj;
use WPML\LIB\WP\Hooks;
use function WPML\FP\spreadArgs;

class Editor implements \IWPML_Backend_Action {

	public function add_hooks() {
		Hooks::onFilter( 'wpml_pb_is_editing_translation_with_native_editor', 10, 2 )
			->then( spreadArgs( [ $this, 'isInlineSavingTranslation' ] ) );
	}

	public function isInlineSavingTranslation( $isTranslationWithNativeEditor, $translatedPostId ) {
		return $isTranslationWithNativeEditor
			|| (
				'heartbeat' === Obj::prop( 'action', $_POST )
				&& self::getInlineSavedPostId() === (int) $translatedPostId
			);
	}

	private static function getInlineSavedPostId() {
		$heartbeatPanels = Obj::path( [ 'data', 'panels' ], $_POST );

		if ( ! is_string( $heartbeatPanels ) ) {
			return null;
		}

		$inlineSave = json_decode( wp_unslash( $heartbeatPanels ), true );

		return is_array( $inlineSave ) && isset( $inlineSave['id'] ) ? (int) $inlineSave['id'] : null;
	}
}
