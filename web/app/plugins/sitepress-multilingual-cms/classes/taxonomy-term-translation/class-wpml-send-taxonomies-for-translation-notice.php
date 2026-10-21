<?php

class WPML_Send_Taxonomies_For_Translation_Notice {

	const NOTICE_ID = 'SendTaxonomiesForTranslationNotice';

	public static function init() {

		$wp_api = new WPML_WP_API();

		if ( current_user_can( 'manage_options' ) && $wp_api->is_taxonomy_translation_page() ) {
			$notices = wpml_get_admin_notices();

			$link_href   = admin_url( 'admin.php?page=tm%2Fmenu%2Fmain.php' );
			$notice_text = sprintf(
			/* translators: Notice on the screen where terms are edited. %1$s: the opening tag of a link to the Translation Dashboard, %2$s: its closing tag. The words between them are the name of that screen. */
				__( 'To translate taxonomies automatically, by your translators or a translation service, use the %1$sTranslation Dashboard%2$s.', 'sitepress' ),
				'<a href="' . esc_url( $link_href ) . '" target="_blank">',
				'</a>'
			);

			$notice = $notices->get_new_notice( self::NOTICE_ID, $notice_text )->set_css_class_types( 'info' );
			$notice->set_css_classes( [ 'send-taxonomies-for-translation-notice' ] );
			$notice->set_dismissible( true );
			$notice->add_display_callback( [ WPML_Taxonomy_Translation_Page::class, 'is_current' ] );
			$notices->add_notice( $notice );
		}

	}

}
