<?php
/**
 * Plugin Name: Advanced Custom Fields Multilingual
 * Description: Adds compatibility between WPML and Advanced Custom Fields | <a href="https://wpml.org/documentation/translating-your-contents/acf/?utm_source=plugin&utm_medium=gui&utm_campaign=acfml">Documentation</a>
 * Author: OnTheGoSystems
 * Plugin URI: https://wpml.org/documentation/wpml-core-and-add-on-plugins/acfml/
 * Author URI: http://www.onthegosystems.com/
 * Version: 5.2.0
 * Text Domain: acfml
 * Domain Path: /languages
 *
 * @package WPML\ACF
 */

if ( get_option( '_wpml_inactive' ) ) {
	return;
}

function acfmlInit() {
	$vendorDir = __DIR__ . '/vendor';

	if ( ! class_exists( 'WPML_Core_Version_Check' ) ) {
		require_once $vendorDir . '/wpml-shared/wpml-lib-dependencies/src/dependencies/class-wpml-core-version-check.php';
	}

	if ( ! WPML_Core_Version_Check::is_ok( __DIR__ . '/wpml-dependencies.json' ) ) {
		return;
	}

	require_once $vendorDir . '/autoload.php';

	define( 'ACFML_VERSION', '5.2.0' );
	define( 'ACFML_PLUGIN_PATH', __DIR__ );
	define( 'ACFML_PLUGIN_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );

	\WPML\Container\share( \ACFML\Container\Config::getSharedClasses() );

	$acfml = \WPML\Container\make( WPML_ACF::class );

	add_action( 'acf/init', [ $acfml, 'init_worker' ], 1 );
	add_action( 'acf/include_fields', [ $acfml, 'initLocalFieldsWorker' ], 1 );

	add_action( 'admin_enqueue_scripts', function() {
		wp_enqueue_style( 'acfml_css', plugin_dir_url( __FILE__ ) . 'assets/css/admin-style.css', [], ACFML_VERSION );
	} );

	load_plugin_textdomain( 'acfml', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

function loadACFMLrequirements() {
	require_once __DIR__ . '/classes/class-wpml-acf-requirements.php';

	$requirements = new WPML_ACF_Requirements();
	$requirements->check_wpml_core();
	$requirements->checkAcf();
}

add_action( 'wpml_loaded', 'acfmlInit' );

add_action( 'plugins_loaded', 'loadACFMLrequirements' );

require_once __DIR__ . '/classes/Notice/Links.php';
require_once __DIR__ . '/classes/Notice/Activation.php';
register_activation_hook( __FILE__, [ \ACFML\Notice\Activation::class, 'activate' ] );

require_once __DIR__ . '/classes/Strings/Activation.php';
register_activation_hook( __FILE__, [ \ACFML\Strings\Activation::class, 'activate' ] );
