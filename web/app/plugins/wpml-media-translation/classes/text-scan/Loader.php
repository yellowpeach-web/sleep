<?php

namespace WPML\Media\TextScan;

class Loader implements \IWPML_Backend_Action_Loader, \IWPML_Frontend_Action_Loader {

	public function create() {
		$services = Services::instance();

		return null === $services ? null : $services->hooks();
	}
}
