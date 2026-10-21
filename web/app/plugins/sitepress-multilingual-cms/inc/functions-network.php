<?php
wp_cache_add_global_groups( array( 'sitepress_ms' ) );

add_action( 'network_admin_menu', 'icl_network_administration_menu' );

\WPML\Request\Adapter\NetworkAdmin::register(
	'deactivatewpml',
	\WPML\Request\Policy\Policy::capability( 'manage_network', \WPML\Request\Policy\Authenticity::actionNonce( 'deactivatewpml', '_wpnonce' ) ),
	'icl_network_deactivate_wpml'
);
\WPML\Request\Adapter\NetworkAdmin::register(
	'activatewpml',
	\WPML\Request\Policy\Policy::capability( 'manage_network', \WPML\Request\Policy\Authenticity::actionNonce( 'activatewpml', '_wpnonce' ) ),
	'icl_network_activate_wpml'
);

function icl_network_administration_menu() {
	add_menu_page(
		/* translators: The name of the plugin, used as the title of its screens and of its menu. It is a product name and stays as it is. */
		__( 'WPML', 'sitepress' ),
		/* translators: The name of the plugin, used as the title of its screens and of its menu. It is a product name and stays as it is. */
		__( 'WPML', 'sitepress' ),
		'manage_sites',
		WPML_PLUGIN_FOLDER . '/menu/network.php',
		null,
		ICL_PLUGIN_URL . '/res/img/icon16.svg'
	);
	add_submenu_page(
		WPML_PLUGIN_FOLDER . '/menu/network.php',
		/* translators: Item in the network admin menu that opens the WPML settings for the whole network. */
		__( 'Network settings', 'sitepress' ),
		/* translators: Item in the network admin menu that opens the WPML settings for the whole network. */
		__( 'Network settings', 'sitepress' ),
		'manage_sites',
		WPML_PLUGIN_FOLDER . '/menu/network.php'
	);
}

function icl_network_deactivate_wpml( $blog_id = false ) {
	global $wpdb;

	$filtered_action = filter_input( INPUT_POST, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_NULL_ON_FAILURE );
	$filtered_action = $filtered_action ? $filtered_action : filter_input( INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_NULL_ON_FAILURE );

	if ( 0 === strcmp( $filtered_action, 'deactivatewpml' ) ) {
		if ( empty( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'deactivatewpml' ) ) {
			return;
		}
	}

	if ( empty( $blog_id ) ) {
		$filtered_id = filter_input( INPUT_POST, 'id', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_NULL_ON_FAILURE );
		$filtered_id = $filtered_id ? $filtered_id : filter_input( INPUT_GET, 'id', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_NULL_ON_FAILURE );
		$blog_id     = $filtered_id !== false ? $filtered_id : $wpdb->blogid;
	}

	if ( $blog_id ) {
		switch_to_blog( $blog_id );
		update_option( '_wpml_inactive', true );
		restore_current_blog();
	}

	wp_redirect( network_admin_url( 'admin.php?page=' . WPML_PLUGIN_FOLDER . '/menu/network.php&updated=true&action=deactivatewpml' ) );
}

function icl_network_activate_wpml( $blog_id = false ) {
	global $wpdb;

	$filtered_action = filter_input( INPUT_POST, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_NULL_ON_FAILURE );
	$filtered_action = $filtered_action ? $filtered_action : filter_input( INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_NULL_ON_FAILURE );

	if ( 0 === strcmp( $filtered_action, 'activatewpml' ) ) {
		if ( empty( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'activatewpml' ) ) {
			return;
		}
	}

	if ( empty( $blog_id ) ) {
			$filtered_id = filter_input( INPUT_POST, 'id', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_NULL_ON_FAILURE );
		$filtered_id     = $filtered_id ? $filtered_id : filter_input( INPUT_GET, 'id', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_NULL_ON_FAILURE );
		$blog_id         = $filtered_id !== false ? $filtered_id : $wpdb->blogid;
	}

	if ( $blog_id ) {
		switch_to_blog( $blog_id );
		delete_option( '_wpml_inactive' );
		restore_current_blog();
	}

	wp_redirect( network_admin_url( 'admin.php?page=' . WPML_PLUGIN_FOLDER . '/menu/network.php&updated=true&action=activatewpml' ) );
	exit();
}
