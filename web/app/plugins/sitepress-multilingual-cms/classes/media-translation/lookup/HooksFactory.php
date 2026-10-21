<?php

namespace WPML\Media\Lookup;

class HooksFactory implements \IWPML_Backend_Action_Loader, \IWPML_Frontend_Action_Loader {

	public function create() {
		return MediaLookupServiceFactory::hooksLoader();
	}
}
