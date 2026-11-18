<?php

namespace ACFML\FieldGroup;

class RestHooksFactory implements \IWPML_REST_Action_Loader {

	public function create() {
		if ( ! wpml_is_rest_request() ) {
			return [];
		}

		return array_merge(
			\ACFML\TranslationPolicy\HooksFactory::editorHooks(),
			[ new LanguagePanelDataHooks() ]
		);
	}
}
