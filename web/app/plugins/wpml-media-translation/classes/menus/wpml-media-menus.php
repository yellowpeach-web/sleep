<?php

class WPML_Media_Menus {

	private $template_service;
	private $sitepress;
	private $wpdb;
	private $pagination;

	public function __construct( WPML_Twig_Template_Loader $template_service, SitePress $sitepress, wpdb $wpdb, ?WPML_Admin_Pagination $pagination = null ) {
		$this->template_service = $template_service;
		$this->sitepress        = $sitepress;
		$this->wpdb             = $wpdb;
		$this->pagination       = $pagination;

	}

	public function display() {
		global $wp_locale, $wpml_query_filter;

		do_action( 'wpml_media_messages' );
		do_action( 'wpml_media_menu' );

		$menu_overrides = apply_filters( 'wpml_media_menu_overrides', array() );
		if ( $menu_overrides ) {
			foreach ( $menu_overrides as $menu_override ) {
				call_user_func( $menu_override );
			}

			return;
		}

		$wpml_media_url     = $this->sitepress->get_wp_api()->constant( 'WPML_MEDIA_URL' );
		$wpml_media_version = $this->sitepress->get_wp_api()->constant( 'WPML_MEDIA_VERSION' );

		wp_enqueue_style( OTGS_Assets_Handles::POPOVER_TOOLTIP );
		wp_enqueue_script( OTGS_Assets_Handles::POPOVER_TOOLTIP );
		wp_enqueue_style( 'otgs-notices' );
		wp_enqueue_style( 'wpml-media', $wpml_media_url . '/res/css/media-translation.css', array(), $wpml_media_version );
		wp_enqueue_script(
			'wpml-media',
			$wpml_media_url . '/res/js/media-translation-popup.js',
			array(
				'jquery',
				'jquery-ui-dialog',
			),
			$wpml_media_version,
			true
		);
		$wpml_media_popup_strings = array(
			/* translators: Name of the Media Translation page in the WPML menu, and the heading at the top of that page. */
			'title'         => esc_js( __( 'Media Translation', 'wpml-media' ) ),
			/* translators: Button label that closes the media translation dialog without saving. Verb, imperative, not the noun "a cancellation". */
			'cancel'        => esc_js( __( 'Cancel', 'wpml-media' ) ),
			'save'          => esc_js( __( 'Save media translation', 'wpml-media' ) ),
			'status_labels' => WPML_Media_Translations_UI::get_translation_status_labels(),
		);
		wp_localize_script( 'wpml-media', 'wpml_media_popup', $wpml_media_popup_strings );
		wp_enqueue_script( 'wpml-media-batch-url-translation', $wpml_media_url . '/res/js/batch-url-translation.js', array( 'jquery' ), $wpml_media_version, true );
		$batch_translation_vars = array(
			/* translators: Message shown on the Media Translation page when the scan for media has finished. */
			'complete'       => esc_js( __( 'Scan complete!', 'wpml-media' ) ),
			'prepare_failed' => esc_js( __( 'The scan could not be started. Refresh this page and try again.', 'wpml-media' ) ),
			/* translators: Message shown on the Media Translation page when one round of the Scan & Replace request failed; the button becomes usable again. */
			'request_failed' => esc_js( __( 'The scan could not be completed. Check your connection and try again.', 'wpml-media' ) ),
			'is_st_enabled'  => (bool) $this->sitepress->get_wp_api()->constant( 'WPML_ST_VERSION' ),
			'nonce'          => wp_create_nonce( 'wpml_media_translate_media_url' ),
		);
		wp_localize_script( 'wpml-media-batch-url-translation', 'wpml_media_batch_translation', $batch_translation_vars );

		wp_enqueue_script( OTGS_Assets_Handles::TABLE_STICKY_HEADER );

		$media_translations_ui = new WPML_Media_Translations_UI(
			$this->sitepress,
			$this->wpdb,
			$wp_locale,
			$wpml_query_filter,
			$this->pagination
		);

		$media_translations_ui->show();
	}


}
