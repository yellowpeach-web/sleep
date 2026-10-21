<?php

namespace ACFML\FieldGroup;

class HooksFactory implements \IWPML_Backend_Action_Loader {

	public function create() {
		return [
			new UIHooks(),
			new CptLockHooks(),
			new SettingsLockHooks( new FieldNamePatterns() ),
			new TranslationModeColumnHooks(),
			new TranslationEditorHooks(),
		];
	}
}
