<?php
/**
 * Plugin Name: WPML Media Translation
 * Plugin URI: https://wpml.org/documentation/wpml-core-and-add-on-plugins/media-translation/
 * Description: Show different images for content in different languages | <a href="https://wpml.org/documentation/translating-your-contents/media/?utm_source=plugin&utm_medium=gui&utm_campaign=wpmlmedia">Documentation</a> | <a href="https://wpml.org/version/wpml-media-translation-5-4-0/">WPML Media Translation 5.4.0 release notes</a>
 * Author: OnTheGoSystems
 * Author URI: http://www.onthegosystems.com/
 * Version: 5.4.0
 * Plugin Slug: wpml-media-translation
 *
 * @package wpml/media
 */

if ( defined( 'WPML_MEDIA_VERSION' ) ) {
	return;
}

if ( ! is_file( __DIR__ . '/vendor/autoload.php' ) || ! is_file( __DIR__ . '/vendor/composer/autoload_real.php' ) ) {
	$wpml_incomplete_plugin_notice = function () {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: the plugin's name. */
					__( '%s is incomplete: some of its files are missing, probably after an interrupted update. Please reinstall the plugin.', 'wpml-media' ),
					'WPML Media Translation'
				)
			)
		);
	};
	add_action( 'admin_notices', $wpml_incomplete_plugin_notice );
	add_action( 'network_admin_notices', $wpml_incomplete_plugin_notice );
	unset( $wpml_incomplete_plugin_notice );
	return;
}

define( 'WPML_MEDIA_VERSION', '5.4.0' );
define( 'WPML_MEDIA_PATH', dirname( __FILE__ ) );

require_once WPML_MEDIA_PATH . '/vendor/autoload.php';

require WPML_MEDIA_PATH . '/inc/constants.inc';
require WPML_MEDIA_PATH . '/inc/wpml-media-dependencies.class.php';
require WPML_MEDIA_PATH . '/inc/wpml-media-upgrade.class.php';
if ( is_admin() ) {
	require_once ABSPATH . 'wp-admin/includes/image.php';
}

function wpml_media_remove_flag_notice() {
	$wpml_admin_notices = wpml_get_admin_notices();
	$wpml_admin_notices->remove_notice(
		WPML_Media_Posts_Media_Flag_Notice::NOTICE_GROUP,
		WPML_Media_Posts_Media_Flag_Notice::NOTICE_ID
	);
}

function wpml_media_core_action_filter_loader() {

	$loaders = array(
		'WPML_Media_Factory',
		'WPML_Media_Save_Translation_Factory',
		'WPML_Media_Get_Attachment_Translation_Data_Factory',
		'WPML_Media_Attachment_Image_Update_Factory',
		'WPML_Media_Screen_Options_Factory',
		'WPML_Media_Posts_Media_Flag_Notice_Factory',
		'WPML_Media_Set_Posts_Media_Flag_Factory',
		'WPML_Media_Set_Posts_Media_Flag_REST_Factory',
		'WPML_Media_Set_Initial_Language_Factory',
		'WPML_Media_Privacy_Content_Factory',
		WPML\Media\Widgets\Block\DisplayTranslation::class,
		WPML\Media\TextScan\Loader::class,
	);

	if ( class_exists( 'WPML_Media_Usage_Factory' ) ) {
		$loaders[] = 'WPML_Media_Post_Media_Usage_Factory';
	}

	$action_filter_loader = new WPML_Action_Filter_Loader();
	$action_filter_loader->load( $loaders );

}

function wpml_media_load_components() {

	if ( class_exists( 'WPML_Current_Screen_Loader_Factory' ) ) {

		$loaders = array(
			'WPML_Media_Post_Images_Translation_Factory',
			'WPML_Media_Post_Batch_Url_Translation_Factory',
			'WPML_Media_Custom_Field_Images_Translation_Factory',
			'WPML_Media_Custom_Field_Images_Translation_REST_Factory',
			'WPML_Media_Custom_Field_Batch_Url_Translation_Factory',
			'WPML_Media_Help_Tab_Factory',
		);

		$action_filter_loader = new WPML_Action_Filter_Loader();
		$action_filter_loader->load( $loaders );
	}
}

function wpml_media_load_components_st() {

	$loaders = array(
		'WPML_Media_String_Images_Translation_Factory',
		'WPML_Media_String_Images_Translation_REST_Factory',
		'WPML_Media_String_Batch_Url_Translation_Factory',
	);

	$action_filter_loader = new WPML_Action_Filter_Loader();
	$action_filter_loader->load( $loaders );

}

function wpml_media_load_components_tm() {

	$loaders = [
		WPML_Media_Populate_Media_Strings_Translations_Factory::class,
	];

	$action_filter_loader = new WPML_Action_Filter_Loader();
	$action_filter_loader->load( $loaders );

}

function wpml_media_boot() {
	remove_action( 'plugins_loaded', 'wpml_media_boot', 0 );

	$media_dependencies = new WPML_Media_Dependencies();
	if ( ! $media_dependencies->check() ) {
		return;
	}

	add_action( 'plugins_loaded', 'wpml_media_core_action_filter_loader', 0 );
	add_action( 'wpml_loaded', 'wpml_media_load_components' );
	add_action( 'wpml_st_loaded', 'wpml_media_load_components_st' );
	add_action( 'wpml_after_tm_loaded', 'wpml_media_load_components_tm' );
}

function wpml_media_boot_after_core() {
	if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
		return;
	}

	remove_action( 'plugin_loaded', 'wpml_media_boot_after_core' );
	remove_action( 'network_plugin_loaded', 'wpml_media_boot_after_core' );
	wpml_media_boot();
}

if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
	wpml_media_boot();
} else {
	add_action( 'plugin_loaded', 'wpml_media_boot_after_core' );
	add_action( 'network_plugin_loaded', 'wpml_media_boot_after_core' );
	add_action( 'plugins_loaded', 'wpml_media_boot', 0 );
}

add_action( 'deactivate_' . WPML_MEDIA_FOLDER . '/plugin.php', 'wpml_media_deactivation_actions' );
function wpml_media_deactivation_actions() {
	if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
		wpml_media_remove_flag_notice();
	}
}
