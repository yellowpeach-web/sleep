<?php

namespace WPML\TM\ATE\BillingContact;

use WPML\TM\ATE\ClonedSites\NoticeActionLinks;

class BillingContactActions implements \IWPML_Backend_Action {
	use NoticeActionLinks;

	const ACTION_PARAM = 'wpml_ate_billing_contact_action';
	const NONCE_ACTION = 'wpml_ate_billing_contact';

	const DISMISS             = 'dismiss';
	const DISMISS_UNREACHABLE = 'dismiss-unreachable';

	const RESULT_PARAM = 'wpml_ate_billing_contact_result';

	public function add_hooks() {
		add_action( 'admin_init', array( $this, 'handle' ) );
	}

	public function handle() {
		$actions = self::dismissActions();
		$action  = self::requestedAction();

		if ( ! isset( $actions[ $action ] ) ) {
			return;
		}

		check_admin_referer( self::NONCE_ACTION );

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		BillingContactState::dismiss( $actions[ $action ] );
		$this->redirectBack( '' );
	}

	public static function dismissUrl( $reason ) {
		return self::actionUrl(
			BillingContactState::REASON_UNREACHABLE === $reason
				? self::DISMISS_UNREACHABLE
				: self::DISMISS
		);
	}

	private static function dismissActions() {
		return array(
			self::DISMISS             => BillingContactState::REASON_MISSING,
			self::DISMISS_UNREACHABLE => BillingContactState::REASON_UNREACHABLE,
		);
	}
}
