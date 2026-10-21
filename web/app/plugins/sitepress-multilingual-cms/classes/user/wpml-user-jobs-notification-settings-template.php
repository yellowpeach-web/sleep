<?php

class WPML_User_Jobs_Notification_Settings_Template {

	const TEMPLATE_FILE = 'job-email-notification.twig';

	private $template_service;

	public function __construct( IWPML_Template_Service $template_service ) {
		$this->template_service = $template_service;
	}

	public function get_setting_section( $notification_input ) {
		$model = $this->get_model( $notification_input );

		return $this->template_service->show( $model, self::TEMPLATE_FILE );
	}

	private function get_model( $notification_input ) {
		$model = array(
			'strings' => array(
				'section_title' => __( 'WPML Translator Settings', 'sitepress' ),
				/* translators: Label in front of the setting that says which emails the user is sent; the choices follow the colon. */
				'field_title'   => __( 'Notification emails:', 'sitepress' ),
				'field_name'    => WPML_User_Jobs_Notification_Settings::BLOCK_NEW_NOTIFICATION_FIELD,
				'field_text'    => __( 'Send me a notification email when there is something new to translate', 'sitepress' ),
				'checked'       => $notification_input,
			),
		);

		return $model;
	}
}
