<?php

namespace WPML\PB\SiteOrigin\Hooks;

class WidgetsCache implements \IWPML_Backend_Action {

	const WIDGETS_TRANSIENT    = 'siteorigin_panels_widgets';
	const CACHE_CLEARED_OPTION = 'wpml_pb_siteorigin_widgets_cache_cleared';

	public function add_hooks() {
		if ( ! get_option( self::CACHE_CLEARED_OPTION ) ) {
			delete_transient( self::WIDGETS_TRANSIENT );
			update_option( self::CACHE_CLEARED_OPTION, 1 );
		}
	}
}
