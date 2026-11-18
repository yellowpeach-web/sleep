<?php

namespace WPML\PB\SiteOrigin\Media;

use WPML\PB\SiteOrigin\Factory;

class HooksFactory implements \IWPML_Backend_Action_Loader, \IWPML_Frontend_Action_Loader {

	public function create() {
		return new \WPML_Page_Builders_Media_Hooks( new UpdateMediaFactory(), Factory::SLUG );
	}
}
