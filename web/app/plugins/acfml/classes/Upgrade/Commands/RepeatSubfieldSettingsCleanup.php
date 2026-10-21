<?php

namespace ACFML\Upgrade\Commands;

use ACFML\Options;

class RepeatSubfieldSettingsCleanup implements Command {

	const KEY = 'repeat-subfield-settings-cleanup';

	public static function run() {
		if ( Options::get( self::KEY ) ) {
			return;
		}

		delete_option( CollapseSubfieldSettings::DONE_OPTION );
		Options::set( self::KEY, true );

		CollapseSubfieldSettings::run();
	}
}
