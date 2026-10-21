<?php
namespace WPML\WPSEO\Shared\Upgrade\Commands;

use WPML\WPSEO\YoastSEO\Terms\Meta\Hooks;

use function WPML\Container\make;

class TranslateExistingTermMeta implements Command {

	const OPTION = 'wpseo_taxonomy_meta';

	public static function run() {
		if ( defined( 'WPSEO_VERSION' ) ) {
			make( Hooks::class )->registerStrings( self::OPTION, get_option( self::OPTION ) );
		}
	}
}
