<?php
/**
 * Plugin Name: WPML SEO
 * Plugin URI: https://wpml.org/documentation/wpml-core-and-add-on-plugins/wpml-seo/
 * Description: Multilingual support for popular SEO plugins
 * Author: OnTheGoSystems
 * Author URI: http://www.onthegosystems.com/
 * Version: 5.2.0
 * Plugin Slug: wp-seo-multilingual
 * Text Domain: wp-seo-multilingual
 * Domain Path: /languages
 * Tested up to: 7.1
 *
 * @package wpml/wpseo
 */

use WPML\WPSEO\YoastSEO\Loaders as YoastSEOLoaders;
use WPML\WPSEO\RankMathSEO\Loaders as RankMathSEOLoaders;
use WPML\WPSEO\IndexNowPlugin\Loaders as IndexNowPluginLoaders;
use WPML\WPSEO\YoastSEO\Utils;
use WPML\Settings\LanguageNegotiation;
use WPML\WPSEO\YoastSEO\Redirects\PostRedirectLanguage;

if ( defined( 'WPSEOML_VERSION' ) ) {
	return;
}

define( 'WPSEOML_VERSION', '5.2.0' );
define( 'WPSEOML_PLUGIN_PATH', __DIR__ );

const WPSEOML_TEXTDOMAIN_BEFORE_UPGRADE_NOTICES = -10;

add_action(
	'init',
	function () {
		load_plugin_textdomain( 'wp-seo-multilingual', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	},
	WPSEOML_TEXTDOMAIN_BEFORE_UPGRADE_NOTICES
);

function wpml_wpseo_loads_first() {
	$path    = str_replace( WP_PLUGIN_DIR . '/', '', __FILE__ );
	$plugins = get_option( 'active_plugins' );
	$key     = array_search( $path, (array) $plugins, true );
	if ( $plugins && $key ) {
		array_splice( $plugins, $key, 1 );
		array_unshift( $plugins, $path );
		update_option( 'active_plugins', $plugins );
	}
}
add_action( 'activated_plugin', 'wpml_wpseo_loads_first', 1 );

if ( ! class_exists( 'WPML_Core_Version_Check' ) ) {
	require_once WPSEOML_PLUGIN_PATH . '/vendor/wpml-shared/wpml-lib-dependencies/src/dependencies/class-wpml-core-version-check.php';
}

if ( ! WPML_Core_Version_Check::is_ok( WPSEOML_PLUGIN_PATH . '/wpml-dependencies.json' ) ) {
	return;
}

require_once WPSEOML_PLUGIN_PATH . '/vendor/autoload.php';

if ( Utils::isPremium() && apply_filters( 'wpml_setting', false, 'setup_complete' ) ) {
	if ( LanguageNegotiation::isDir() ) {
		$redirector = new WPML_WPSEO_Redirection();
		if ( $redirector->is_redirection() ) {
			add_filter( 'wpml_skip_convert_url_string', '__return_true' );
		}
	} elseif ( ! is_admin() ) {
		( new PostRedirectLanguage() )->scopeFrontEndRedirectsToCurrentLanguage();
	}
}

function wpml_wpseo_init() {
	if ( defined( 'WPSEO_VERSION' ) ) {
		$actions_filters_loader = new WPML_Action_Filter_Loader();
		$actions_filters_loader->load( YoastSEOLoaders::get( WPSEO_VERSION ) );
	}

	if ( defined( 'RANK_MATH_VERSION' ) ) {
		$actions_filters_loader = new WPML_Action_Filter_Loader();
		$actions_filters_loader->load( RankMathSEOLoaders::get() );
	}

	if ( defined( 'BWT_INDEXNOW_PLUGIN_VERSION' ) ) {
		$actions_filters_loader = new WPML_Action_Filter_Loader();
		$actions_filters_loader->load( IndexNowPluginLoaders::get() );
	}
}
add_action( 'wpml_loaded', 'wpml_wpseo_init' );
