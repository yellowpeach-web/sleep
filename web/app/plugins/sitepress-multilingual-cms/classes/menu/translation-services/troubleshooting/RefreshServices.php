<?php

namespace WPML\TM\Menu\TranslationServices\Troubleshooting;

class RefreshServices {

	const AJAX_ACTION = 'wpml_tm_refresh_services';

	private $template;

	private $tp_services;

	public function __construct( \IWPML_Template_Service $template, \WPML_TP_API_Services $tp_services ) {
		$this->template    = $template;
		$this->tp_services = $tp_services;
	}

	public function add_hooks() {
		\WPML\Request\Adapter\Ajax::register( self::AJAX_ACTION, \WPML\Request\Policy\Policy::capability( 'manage_translations', \WPML\Request\Policy\Authenticity::actionNonce( self::AJAX_ACTION, 'nonce' ) ), array( $this, 'refresh_services_ajax_handler' ) );
	}

	public function refresh_services_ajax_handler() {
		if ( \WPML\Setup\Initializer::rejectSettingsMutationAjax() ) {
			return;
		}

		if ( $this->is_valid_request() ) {
			if ( $this->refresh_services() ) {
				wp_send_json_success(
					array(
						/* translators: Message shown after the list of translation services was read again. */
						'message' => __( 'Services Refreshed.', 'sitepress' ),
					)
				);
			} else {
				wp_send_json_error(
					array(
						'message' => __(
							'WPML cannot load the list of translation services. This can be a connection problem. Please wait a minute and reload this page.
 If the problem continues, please contact WPML support.',
							'sitepress'
						),
					)
				);
			}
		} else {
			wp_send_json_error(
				array(
					/* translators: Error message returned when a request from the browser cannot be trusted and is turned away. */
					'message' => __( 'Invalid Request.', 'sitepress' ),
				)
			);
		}
	}

	public function refresh_services() {
		return $this->tp_services->refresh_cache() && $this->refresh_active_service();
	}

	private function refresh_active_service() {
		$active_service = $this->tp_services->get_active();

		if ( $active_service ) {
			$active_service = (object) (array) $active_service;
			\TranslationProxy::build_and_store_active_translation_service( $active_service, $active_service->custom_fields_data );
		}

		return true;
	}

	private function is_valid_request() {
		return isset( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'], self::AJAX_ACTION );
	}
}
