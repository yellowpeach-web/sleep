<?php

namespace ACFML\TranslationPolicy;

use ACFML\FieldGroup\TranslationEditorHooks;
use ACFML\TranslationEditor\DisableHooks;
use WPML\TranslationPolicy\CutoverSet;

class HooksFactory implements \IWPML_Backend_Action_Loader, \IWPML_Frontend_Action_Loader {

	public function create() {
		if ( self::isCutOver() ) {
			global $wpdb;

			return [
				new LabelHooks(),
				new LockHooks( new LockRows( $wpdb ), [ self::class, 'isCutOver' ] ),
			];
		}

		return [ new DisableHooks() ];
	}

	public static function editorHooks() {
		return self::isCutOver() ? [] : [ new TranslationEditorHooks() ];
	}

	public static function addCutoverStep() {
		add_filter( 'wpml_upgrade_commands', CutoverSet::addOnStep( WriteLocks::class ), 10, 2 );
	}

	public static function isCutOver() {
		return function_exists( 'wpml_translation_policy_enabled' ) && wpml_translation_policy_enabled();
	}
}
