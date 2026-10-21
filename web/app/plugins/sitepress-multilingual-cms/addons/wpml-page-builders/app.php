<?php

if ( ! defined( 'WPML_PAGE_BUILDERS_LOADED' ) ) {
	throw new Exception( 'This file should be called from the loader only.' );
}

require_once __DIR__ . '/classes/OldPlugin.php';
if ( WPML\PB\OldPlugin::handle() ) {
	return;
}

define( 'WPML_PAGE_BUILDERS_VERSION', '5.2.0' );
define( 'WPML_PAGE_BUILDERS_PATH', __DIR__ );

\WPML\PB\App::run();
