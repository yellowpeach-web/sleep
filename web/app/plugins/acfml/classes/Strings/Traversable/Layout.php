<?php

namespace ACFML\Strings\Traversable;

use ACFML\Strings\Config;

class Layout extends Entity {

	protected function getConfig() {
		return Config::getForLayout();
	}
}
