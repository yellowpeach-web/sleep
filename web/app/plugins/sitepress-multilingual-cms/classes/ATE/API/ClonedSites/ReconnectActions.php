<?php

namespace WPML\TM\ATE\ClonedSites;

class ReconnectActions implements \IWPML_Backend_Action, \IWPML_DIC_Action {
	use NoticeActionLinks;

	const ACTION_PARAM = 'wpml_ate_reconnect_action';
	const NONCE_ACTION = 'wpml_ate_reconnect';
	const RETRY        = 'retry';
	const DISMISS      = 'dismiss';

	const RESULT_PARAM = 'wpml_ate_reconnect_result';
	const RESULT_TRIED = 'tried';

	private $auth;

	public function __construct( \WPML_TM_ATE_Authentication $auth ) {
		$this->auth = $auth;
	}

	public function add_hooks() {
		add_action( 'admin_init', [ $this, 'handle' ], 5 );

		add_action( 'admin_notices', [ $this, 'renderResult' ], 5 );
	}

	public function handle() {
		$action = self::requestedAction();

		if ( self::RETRY !== $action && self::DISMISS !== $action ) {
			return;
		}

		check_admin_referer( self::NONCE_ACTION );

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( self::RETRY === $action ) {
			ReconnectState::makeDue();
			$this->redirectBack( self::RESULT_TRIED );

			return;
		}

		ReconnectState::dismissEscalation( ReconnectState::diagnosticCode( (string) $this->auth->get_site_id() ) );
		$this->redirectBack( '' );
	}

	public function renderResult() {
		$result = self::requestedResult();

		if ( self::RESULT_TRIED !== $result ) {
			return;
		}

		if ( ReconnectState::openedByCreate() ) {
			return;
		}

		if ( ReconnectState::get() ) {
			$message = esc_html__( 'WPML tried to reconnect just now and it did not work yet.', 'sitepress' );
			$class   = 'notice notice-warning';
		} else {
			$message = esc_html__( 'WPML reconnected this site to our translation system.', 'sitepress' );
			$class   = 'notice notice-success';
		}

		echo '<div class="' . esc_attr( $class ) . ' wpml-ate-reconnect-result"><p>'
			. $message
			. '</p></div>';
	}

	public static function retryUrl() {
		return self::actionUrl( self::RETRY );
	}

	public static function dismissUrl() {
		return self::actionUrl( self::DISMISS );
	}
}
