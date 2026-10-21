<?php

namespace ACFML\FieldGroup;

use WPML\LIB\WP\Hooks;
use function WPML\FP\spreadArgs;

class TranslationEditorHooks implements \IWPML_Backend_Action {

	public function add_hooks() {
		Hooks::onFilter( 'wpml_use_tm_editor', 10, 2 )
			->then( spreadArgs( [ $this, 'disableTranslationEditor' ] ) );
	}

	public function disableTranslationEditor( $useEditor, $postId ) {
		$alreadyDisabled = ! $useEditor;

		if ( $alreadyDisabled ) {
			return false;
		}

		if ( ! Cache::hasLocalizationGroup() ) {
			return $useEditor;
		}

		return Mode::LOCALIZATION !== Mode::getForFieldGroups( Cache::getForPost( $postId ) );
	}
}
