<?php

add_action( 'init', 'icl_load_hacks' );

function icl_load_hacks() {
	if ( file_exists( WPML_PLUGIN_PATH . '/inc/hacks/misc-constants.php' ) ) {
		include_once WPML_PLUGIN_PATH . '/inc/hacks/misc-constants.php';
	}
	include_once WPML_PLUGIN_PATH . '/inc/hacks/language-canonical-redirects.php';
}


require WPML_PLUGIN_PATH . '/inc/hacks/missing-php-functions.php';
