<?php

namespace WPML\WPSEO\Shared\Upgrade\Commands;

use WPML\WPSEO\Shared\Options;
use WPML\WPSEO\YoastSEO\Indexable\SiteLinksStoredAsExternalRepair;
use function WPML\Container\make;

class RebuildYoastSiteLinksStoredAsExternal implements Command {

	const KEY_COMPLETED = 'command_rebuild_yoast_site_links_stored_as_external_complete';

	public static function run() {
		if ( Options::get( self::KEY_COMPLETED ) ) {
			return;
		}

		if ( defined( 'WPSEO_VERSION' ) ) {
			make( SiteLinksStoredAsExternalRepair::class )->queueRebuild();
		}

		Options::set( self::KEY_COMPLETED, true );
	}
}
