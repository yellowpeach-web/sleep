<?php

class WPML_Theme_Plugin_Localization_Options_UI implements IWPML_Theme_Plugin_Localization_UI_Strategy {

	private $sitepress;

	public function __construct( SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function get_model() {
		$model = array(
			'nonce_field'            => WPML_Theme_Plugin_Localization_Options_Ajax::NONCE_LOCALIZATION_OPTIONS,
			'nonce_value'            => wp_create_nonce( WPML_Theme_Plugin_Localization_Options_Ajax::NONCE_LOCALIZATION_OPTIONS ),
			/* translators: Heading of the section about how the texts of the theme and plugins are translated. */
			'section_label'          => __( 'Localization options', 'sitepress' ),
			'top_options'            => array(
				array(
					'template' => 'automatic-load-check.twig',
					'model'    => array(
						'theme_localization_load_textdomain' => array(
							'value'   => 1,
							'label'   => __( "Automatically load the theme's .mo file using 'load_textdomain'", 'sitepress' ),
							'checked' => checked( $this->sitepress->get_setting( 'theme_localization_load_textdomain' ), true, false ),
						),
						'gettext_theme_domain_name' => array(
							'value' => $this->sitepress->get_setting( 'gettext_theme_domain_name', '' ),
							/* translators: Label in front of the field where the name a theme or plugin uses for its texts is typed. "textdomain" is a technical name and stays as it is. */
							'label' => __( 'Enter textdomain:', 'sitepress' ),
						),
					),
				),
			),
			/* translators: Button label that keeps what was entered. Verb, imperative. */
			'button_label'           => __( 'Save', 'sitepress' ),
			'scanning_progress_msg'  => __( "Scanning now, please don't close this page.", 'sitepress' ),
			/* translators: Heading above the list of texts found when the theme or a plugin was gone through. */
			'scanning_results_title' => __( 'Scanning Results', 'sitepress' ),
		);

		return apply_filters( 'wpml_localization_options_ui_model', $model );
	}

	public function get_template() {
		return 'options.twig';
	}
}
