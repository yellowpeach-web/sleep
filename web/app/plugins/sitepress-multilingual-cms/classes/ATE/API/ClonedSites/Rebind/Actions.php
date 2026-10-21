<?php

namespace WPML\TM\ATE\ClonedSites\Rebind;

class Actions implements \IWPML_Backend_Action, \IWPML_DIC_Action {
	use \WPML\TM\ATE\ClonedSites\NoticeActionLinks;

	const ACTION_PARAM = 'wpml_ate_rebind_action';
	const NONCE_ACTION = 'wpml_ate_rebind';
	const START_FRESH  = 'start-fresh';
	const DISMISS      = 'dismiss';

	const RESULT_PARAM  = 'wpml_ate_rebind_result';
	const RESULT_FRESH  = 'fresh';
	const RESULT_FAILED = 'fresh-failed';

	public function add_hooks() {
		add_action( 'admin_init', [ $this, 'handle' ], 5 );

		add_action( 'admin_notices', [ $this, 'renderResult' ], 5 );
	}

	public function handle() {
		$action = self::requestedAction();

		if ( self::START_FRESH !== $action && self::DISMISS !== $action ) {
			return;
		}

		check_admin_referer( self::NONCE_ACTION );

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( self::DISMISS === $action ) {
			State::clear();
			$this->redirectBack( '' );

			return;
		}

		$this->redirectBack(
			$this->startFresh() ? self::RESULT_FRESH : self::RESULT_FAILED
		);
	}

	protected function startFresh() {
		return StartFresh::build()->run();
	}

	public function renderResult() {
		$result = self::requestedResult();

		if ( self::RESULT_FRESH === $result ) {
			$message = esc_html__( 'This site is now on a new, empty translation project.', 'sitepress' );
			$class   = 'notice notice-success';
		} elseif ( self::RESULT_FAILED === $result ) {
			$message = esc_html__(
				'WPML could not start a new translation project just now, so nothing changed. Try again in a few minutes, or contact WPML support if it keeps failing.',
				'sitepress'
			);
			$class   = 'notice notice-warning';
		} else {
			return;
		}

		echo '<div class="' . esc_attr( $class ) . ' wpml-ate-rebind-result"><p>'
			. $message
			. '</p></div>';
	}

	public static function startFreshUrl() {
		return self::actionUrl( self::START_FRESH );
	}

	public static function dismissUrl() {
		return self::actionUrl( self::DISMISS );
	}
}
