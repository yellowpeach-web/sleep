<?php

class WPML_Display_As_Translated_Default_Lang_Messages_Factory extends WPML_Current_Screen_Loader_Factory {

	public function create_hooks() {
		global $sitepress;

		$template_service_loader = new WPML_Twig_Template_Loader(
			array( WPML_PLUGIN_PATH . '/templates/display-as-translated' )
		);

		return new WPML_Display_As_Translated_Default_Lang_Messages(
			$sitepress,
			new WPML_Display_As_Translated_Default_Lang_Messages_View( $template_service_loader->get_template() )
		);
	}

	public function get_screen_regex() {
		// license (no TM) case, where Settings is not the TM page.
		return defined( 'WPML_TM_FOLDER' )
			? '/(^sitepress-multilingual-cms\/menu\/languages$|wpml_page_' . WPML_TM_FOLDER . '\/menu\/settings)/'
			: '/^sitepress-multilingual-cms\/menu\/languages$/';
	}

	public function create() {
		if ( defined( 'WPML_TM_FOLDER' )
			 && WPML_TM_Subview_Detection::is_on_settings_page()
			 && 'languages' !== WPML_TM_Subview_Detection::current_subview() ) {
			return null;
		}

		return parent::create();
	}
}
