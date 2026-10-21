<?php

namespace WPML\TranslationMode;

use WPML\Setup\Option;

class ClearAiSkippedOnAiSettings implements \IWPML_Backend_Action, \IWPML_REST_Action {

	public function add_hooks() {
		add_action( 'wpml_ai_translation_settings_rendered', array( $this, 'clearAiSkippedIfEngineActive' ), 10, 1 );
	}

	public function clearAiSkippedIfEngineActive( $engineActive ) {
		if ( $engineActive && Option::getAiSkipped() ) {
			Option::setAiSkipped( false );
		}
	}
}
