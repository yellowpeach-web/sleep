<?php

namespace ACFML\Strings\Traversable;

use ACFML\Strings\Config;
use ACFML\Strings\Helper\ContentTypeLabels;
use WPML\FP\Obj;

class OptionsPage extends Entity {

	protected $idKey = 'menu_slug';

	protected function getConfig() {
		return Config::getForOptionsPage();
	}

}
