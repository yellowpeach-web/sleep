<?php

namespace ACFML\Strings\Traversable;

use ACFML\Strings\Config;

class FieldGroup extends Entity {

	protected function getConfig() {
		return Config::getForGroup();
	}
}
