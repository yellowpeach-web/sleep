<?php

namespace ACFML\Repeater\Sync;

use ACFML\Repeater\Shuffle\Post;

class RestHooksFactory implements \IWPML_REST_Action_Loader {

	public function create() {
		if ( ! wpml_is_rest_request() ) {
			return [];
		}

		return [
			new Rest\SetSyncOptionController(),
			new Rest\DefaultChoiceHooks( new CheckboxCondition( new Post() ) ),
		];
	}
}
