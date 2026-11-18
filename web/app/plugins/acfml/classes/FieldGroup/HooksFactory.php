<?php

namespace ACFML\FieldGroup;

class HooksFactory implements \IWPML_Backend_Action_Loader {

	public function create() {
		return array_merge(
			[
				new UIHooks(),
				new CptLockHooks(),
				new SettingsLockHooks( new FieldNamePatterns() ),
				new TranslationModeColumnHooks(),
			],
			\ACFML\TranslationPolicy\HooksFactory::editorHooks()
		);
	}
}
