<?php

namespace ACFML\Cpt;

use ACFML\Helper\Cpt;
use ACFML\TranslationDataColumnHooks;
use ACFML\TranslationDataMetaboxHooks;

class HooksFactory implements \IWPML_Backend_Action_Loader {

	public function create() {
		$cptHelper = new Cpt();
		return [
			new TranslationDataColumnHooks( $cptHelper ),
			new TranslationDataMetaboxHooks( $cptHelper ),
		];
	}
}
