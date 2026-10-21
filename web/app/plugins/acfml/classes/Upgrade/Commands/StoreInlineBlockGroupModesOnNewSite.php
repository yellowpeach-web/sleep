<?php

namespace ACFML\Upgrade\Commands;

use ACFML\FieldGroup\InlineBlockGroupModes;

class StoreInlineBlockGroupModesOnNewSite implements Command {

	public static function run() {
		InlineBlockGroupModes::startStoringOnNewSite();
	}
}
