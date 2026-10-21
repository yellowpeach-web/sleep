<?php

namespace ACFML\Upgrade;

use ACFML\Upgrade\Commands\CollapseSubfieldSettings;
use ACFML\Upgrade\Commands\MigrateToV2;
use ACFML\Upgrade\Commands\MigrateToV2_1;
use ACFML\Upgrade\Commands\MigrateToV2_2;
use ACFML\Upgrade\Commands\RegisterMissingStrings;
use ACFML\Upgrade\Commands\RememberCodeGroupModes;
use ACFML\Upgrade\Commands\RenameFieldGroupPackages;
use ACFML\Upgrade\Commands\RepeatSubfieldSettingsCleanup;
use ACFML\Upgrade\Commands\StoreInlineBlockGroupModesOnNewSite;

class CommandsProvider {

	public static function get() {
		return wpml_collect( [
			MigrateToV2::class,
			MigrateToV2_1::class,
			MigrateToV2_2::class,
			CollapseSubfieldSettings::class,
			RepeatSubfieldSettingsCleanup::class,
			RegisterMissingStrings::class,
			RememberCodeGroupModes::class,
			StoreInlineBlockGroupModesOnNewSite::class,
			RenameFieldGroupPackages::class,
		] );
	}

	public static function getHash() {
		return md5( self::get()->implode( ',' ) );
	}
}
