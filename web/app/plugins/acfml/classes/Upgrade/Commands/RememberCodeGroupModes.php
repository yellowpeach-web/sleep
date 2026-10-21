<?php

namespace ACFML\Upgrade\Commands;

use ACFML\FieldGroup\CodeGroupModes;
use ACFML\Options;
use ACFML\Upgrade\Upgrade;
use WPML\LIB\WP\Hooks;

class RememberCodeGroupModes implements Command {

	public static function run() {
		$isUpgradeFromAnOlderVersion = null !== Options::get( Upgrade::KEY_LAST_MIGRATION_HASH );

		Hooks::onAction( 'wp_loaded', MigrateToV2::WP_LOADED_PRIORITY + 1 )
			->then( function() use ( $isUpgradeFromAnOlderVersion ) {
				$isExistingSite = $isUpgradeFromAnOlderVersion || MigrateToV2::isUpgradeFromVersionOne();

				( new CodeGroupModes() )->rememberLocalGroups( $isExistingSite );
			} );
	}
}
