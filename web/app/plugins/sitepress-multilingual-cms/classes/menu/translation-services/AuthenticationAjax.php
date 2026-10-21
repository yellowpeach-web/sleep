<?php

namespace WPML\TM\Menu\TranslationServices;

use TranslationProxy;
use TranslationProxy_Service;
use WPML\TM\TranslationProxy\Services\AuthorizationFactory;
use WPMLTranslationProxyApiException;

class AuthenticationAjax {

	const AJAX_ACTION = 'translation_service_authentication';

	protected $authorize_factory;

	public function __construct( AuthorizationFactory $authorize_factory ) {
		$this->authorize_factory = $authorize_factory;
	}

	public function add_hooks() {
		\WPML\Request\Adapter\Ajax::register( 'translation_service_authentication', \WPML\Request\Policy\Policy::capability( 'manage_translations', \WPML\Request\Policy\Authenticity::actionNonce( 'translation_service_authentication', 'nonce' ) ), [ $this, 'authenticate_service' ] );
		\WPML\Request\Adapter\Ajax::register( 'translation_service_update_credentials', \WPML\Request\Policy\Policy::capability( 'manage_translations', \WPML\Request\Policy\Authenticity::actionNonce( 'translation_service_authentication', 'nonce' ) ), [ $this, 'update_credentials' ] );
		\WPML\Request\Adapter\Ajax::register( 'translation_service_enable_unlisted_service', \WPML\Request\Policy\Policy::capability( 'manage_translations', \WPML\Request\Policy\Authenticity::actionNonce( 'translation_service_authentication', 'nonce' ) ), [ $this, 'enable_unlisted_service' ] );
		\WPML\Request\Adapter\Ajax::register( 'translation_service_invalidation', \WPML\Request\Policy\Policy::capability( 'manage_translations', \WPML\Request\Policy\Authenticity::actionNonce( 'translation_service_authentication', 'nonce' ) ), [ $this, 'invalidate_service' ] );
	}

	public function authenticate_service() {
		$this->handle_action(
			function () {
				$this->authorize_factory->create()->authorize(
					json_decode( stripslashes( $_POST['custom_fields'] ) )
				);
			},
			[ $this, 'is_valid_request_with_params' ],
			/* translators: Message shown after a translation service was set up and can be used. */
			__( 'Service activated.', 'sitepress' ),
			__(
				'The authentication didn\'t work. Please make sure you entered your details correctly and try again.',
				'sitepress'
			)
		);
	}

	public function update_credentials() {
		$this->handle_action(
			function () {
				$this->authorize_factory->create()->updateCredentials(
					json_decode( stripslashes( $_POST['custom_fields'] ) )
				);
			},
			[ $this, 'is_valid_request_with_params' ],
			__( 'Service credentials updated.', 'sitepress' ),
			__(
				'The authentication didn\'t work. Please make sure you entered your details correctly and try again.',
				'sitepress'
			)
		);
	}

	public function enable_unlisted_service() {
		if ( \WPML\Setup\Initializer::rejectSettingsMutationAjax() ) {
			return;
		}

		if ( ! $this->is_valid_nonce_request() ) {
			/* translators: Title of the message shown when a request could not be carried out. */
			$this->send_error( __( 'Invalid Request', 'sitepress' ), __( 'Reload the page and try again.', 'sitepress' ) );
		}

		$suid = isset( $_POST['suid'] ) ? sanitize_text_field( $_POST['suid'] ) : '';

		if ( ! $suid ) {
			/* translators: Title of the message shown when a request could not be carried out. */
			$this->send_error( __( 'Invalid Request', 'sitepress' ), __( 'The field can\'t be empty.', 'sitepress' ) );

			return;
		}

		try {
			$service = TranslationProxy_Service::get_service_by_suid( $suid );
		} catch ( WPMLTranslationProxyApiException $e ) {
			$this->send_error(
				__( 'We couldn\'t find a translation service connected to this key.', 'sitepress' ),
				__( 'Please contact your translation service and ask them to provide you with this information.', 'sitepress' )
			);

			return;
		}

		$result = \WPML\TM\Menu\TranslationServices\Endpoints\Select::select( $service->id );

		if ( is_string( $result ) ) {
			/* translators: Title of the message shown when the server answered with a problem. */
			$this->send_error( __( 'Error Server', 'sitepress' ), __( 'Unable to set this service as default.', 'sitepress' ) );

			return;
		}
		$this->send_success_response( __( 'Service added and set as default.', 'sitepress' ) );
	}

	public function invalidate_service() {
		$this->handle_action(
			function () {
				$this->authorize_factory->create()->deauthorize();
			},
			[ $this, 'is_valid_nonce_request' ],
			/* translators: Message shown after the account details of a translation service are dropped, so the service can no longer be used. */
			__( 'Service invalidated.', 'sitepress' ),
			__( 'Unable to invalidate this service. Please contact WPML support.', 'sitepress' )
		);
	}

	private function handle_action(
		callable $action,
		callable $request_validation,
		$success_message,
		$failure_message
	) {
		if ( \WPML\Setup\Initializer::rejectSettingsMutationAjax() ) {
			return;
		}

		if ( $request_validation() ) {
			try {
				$action();

				$this->send_success_response( $success_message );
			} catch ( \Exception $e ) {
				return $this->send_error_message( $failure_message );
			}
		} else {
			/* translators: Title of the message shown when a request could not be carried out. */
			$this->send_error_message( __( 'Invalid Request', 'sitepress' ) );
		}
	}

	private function send_success_response( $msg ) {
		wp_send_json_success(
			[
				'errors'  => 0,
				'message' => $msg,
				'reload'  => 1,
			]
		);
	}

	private function send_error_message( $msg ) {
		wp_send_json_error(
			[
				'errors'  => 1,
				'message' => $msg,
				'reload'  => 0,
			]
		);
	}

	private function send_error( $title, $description ) {
		wp_send_json_error(
			[
				'errors'      => 1,
				'title'       => $title,
				'description' => $description,
				'reload'      => 0,
			],
			400
		);
	}

	public function is_valid_nonce_request() {
		return isset( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'], self::AJAX_ACTION );
	}

	public function is_valid_request_with_params() {
		return isset( $_POST['service_id'], $_POST['custom_fields'] ) && $this->is_valid_nonce_request();
	}
}
