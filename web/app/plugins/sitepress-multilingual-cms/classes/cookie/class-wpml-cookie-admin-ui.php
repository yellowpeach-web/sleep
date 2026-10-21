<?php

class WPML_Cookie_Admin_UI {

	const BOX_TEMPLATE = 'admin-cookie-box.twig';
	const BUTTON_ID    = 'js-wpml-store-frontend-cookie';

	private $template_service;

	private $cookie_setting;

	public function __construct( WPML_Twig_Template $template_service, WPML_Cookie_Setting $cookie_setting ) {
		$this->template_service = $template_service;
		$this->cookie_setting   = $cookie_setting;
	}

	public function add_hooks() {
		add_action( 'wpml_after_settings', array( $this, 'render_cookie_box' ) );
	}

	public function render_cookie_box() {
		echo $this->template_service->show( $this->get_model(), self::BOX_TEMPLATE );
	}

	private function get_model() {
		return array(
			'strings'          => array(
				'title'       => __( 'Language filtering for AJAX operations', 'sitepress' ),
				'field_name'  => WPML_Cookie_Setting::COOKIE_SETTING_FIELD,
				'field_label' => __( 'Store a language cookie for visitors', 'sitepress' ),
				'tooltip'     => sprintf(
					/* translators: %s is the cookie name, shown on its own line. */
					__( 'Many hosts do not cache pages for visitors who have this cookie. WPML finds the language of AJAX requests without it. Turn this off for faster pages, unless your theme or a plugin reads this cookie: %s', 'sitepress' ),
					'<br><code>wp-wpml_current_language</code>'
				),
				/* translators: Button label that keeps what was entered. Verb, imperative. */
				'button_text' => __( 'Save', 'sitepress' ),
				'button_id'   => self::BUTTON_ID,
			),
			'ajax_response_id' => WPML_Cookie_Setting_Ajax::AJAX_RESPONSE_ID,
			'nonce_field'      => WPML_Cookie_Setting_Ajax::NONCE_COOKIE_SETTING,
			'nonce_value'      => wp_create_nonce( WPML_Cookie_Setting_Ajax::NONCE_COOKIE_SETTING ),
			'checked'          => checked( $this->cookie_setting->get_setting(), true, false ),
		);
	}
}
