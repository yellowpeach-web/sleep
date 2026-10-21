<?php

use WPML\Core\WP\App\Resources;

class WPML_WP_Options_General_Hooks implements IWPML_Action {

	public function add_hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}

	public function admin_enqueue_scripts( $hook ) {
		wp_enqueue_script(
			'wpml-options-general',
			ICL_PLUGIN_URL . '/dist/js/wp-options-general/app.js',
			array( Resources::vendorAsDependency() ),
			ICL_SITEPRESS_SCRIPT_VERSION
		);

		$site_language_link = '<a href="' . admin_url( 'admin.php?page=' . WPML_TM_FOLDER . '/menu/settings&section=languages#lang-sec-1' ) . '">' .
			/* translators: Link text on the WordPress general settings screen that opens the WPML screen where the languages of the site are set. It is the name of that screen. */
			esc_html__( 'WPML Site Languages section', 'sitepress' ) .
			'</a>';

		$profile_language_link = '<a href="' . admin_url( 'profile.php' ) . '">' .
			/* translators: Link text on the WordPress general settings screen that opens the part of the user's own profile where the admin language is chosen. */
			esc_html__( 'Language section', 'sitepress' ) .
			'</a>';

		$message = sprintf(
		/* translators: Note WPML adds to the WordPress general settings screen. %1$s: a link whose text is "WPML Site Languages section", %2$s: a link whose text is "Language section"; both are already wrapped in their tags. */
            __( 'With WPML activated, you can set your site’s languages from the %1$s.<br>To change the language of your WordPress admin, go to the %2$s in your user profile.', 'sitepress' ),
			$site_language_link,
			$profile_language_link
		);

		wp_localize_script( 'wpml-options-general', 'wpmlOptionsGeneral', array( 'languageSelectMessage' => $message ) );
	}

}
