<?php

namespace ACFML\Repeater\Sync;

class OutsideEditorSaveHooksFactory implements \IWPML_Backend_Action_Loader, \IWPML_Frontend_Action_Loader {

	public function create() {
		return new OutsideEditorSaveHooks( TranslationStatusFactory::fromGlobals() );
	}
}
