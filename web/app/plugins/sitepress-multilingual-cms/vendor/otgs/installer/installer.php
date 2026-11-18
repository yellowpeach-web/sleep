<?php


if ( function_exists( 'WP_Installer' ) ) {
	$otgs_installer_loaded_core = ( new ReflectionFunction( 'WP_Installer' ) )->getFileName();
	$otgs_installer_loaded_core = $otgs_installer_loaded_core ? realpath( $otgs_installer_loaded_core ) : false;
	if ( $otgs_installer_loaded_core && realpath( __DIR__ . '/includes/functions-core.php' ) !== $otgs_installer_loaded_core ) {
		$otgs_installer_loaded_bootfile = realpath( dirname( dirname( $otgs_installer_loaded_core ) ) . '/installer.php' );
		$otgs_installer_registered      = isset( $GLOBALS['wp_installer_instances'] ) ? (array) $GLOBALS['wp_installer_instances'] : [];
		foreach ( $otgs_installer_registered as $otgs_installer_instance ) {
			if ( isset( $otgs_installer_instance['bootfile'] ) && realpath( $otgs_installer_instance['bootfile'] ) === $otgs_installer_loaded_bootfile ) {
				$delegate = $otgs_installer_instance;
				break;
			}
		}
		if ( $otgs_installer_loaded_bootfile ) {
			include_once $otgs_installer_loaded_bootfile;
		}

		return;
	}
}

include_once untrailingslashit( plugin_dir_path( __FILE__ ) ) . '/includes/class-otgs-installer-wp-share-local-components-setting.php';

if ( version_compare( $delegate['version'], '1.8.12', '>=' ) ) {
	define( 'WP_INSTALLER_VERSION', $delegate['version'] );
}

include_once dirname( __FILE__ ) . '/includes/class-otgs-installer-autoloader.php';

$autoload = new OTGS_Installer_Autoloader();
$autoload->initialize();

WP_Installer();
WP_Installer_Channels();

$installer_loader = new OTGS_Installer_Loader( get_OTGS_Installer_Factory() );
$installer_loader->init();
