<?php

namespace WPML\TM\ATE\BillingContact;

use WPML\UIPage;

class BillingContactNotice implements \IWPML_Backend_Action {

	public function add_hooks() {
		add_action( 'all_admin_notices', array( $this, 'render' ) );
	}

	public function render() {
		$reason = $this->reasonToRender();

		if ( '' === $reason ) {
			return;
		}

		echo $this->markup( $reason );
	}

	private function reasonToRender() {
		if ( ! \WPML_TM_ATE_Status::is_active() ) {
			return '';
		}

		$this->forgetWhatAmsHasAnswered();

		$reason = BillingContactState::currentReason();

		if ( '' === $reason ) {
			return '';
		}

		if ( BillingContactState::isDismissed( $reason ) ) {
			return '';
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		return $this->isOnSupportedScreen() ? $reason : '';
	}

	private function forgetWhatAmsHasAnswered() {
		foreach ( BillingContactState::reasons() as $reason ) {
			if ( BillingContactState::isResolved( $reason ) ) {
				BillingContactState::clearDismissal( $reason );
			}
		}
	}

	private function isOnSupportedScreen() {
		if ( UIPage::isTMDashboard( $_GET ) ) {
			return true;
		}

		return isset( $GLOBALS['pagenow'] ) && 'index.php' === $GLOBALS['pagenow'];
	}

	private function markup( $reason ) {
		$heading = esc_html__( 'Make sure you hear about translation issues.', 'sitepress' );

		$add     = esc_html__( 'Add the translation contact', 'sitepress' );
		/* translators: Button label that closes a notice and keeps it from coming back. Verb, imperative. */
		$dismiss = esc_html__( 'Dismiss', 'sitepress' );

		return '<div class="notice notice-warning wpml-ate-billing-contact-missing">'
		       . '<p><strong>' . $heading . '</strong></p>'
		       . '<p>' . $this->body( $reason ) . '</p>'
		       . '<p><a class="button button-primary wpml-ate-billing-contact-add" href="'
		       . esc_url( self::settingsUrl() ) . '">' . $add . '</a></p>'
		       . '<p><a class="wpml-ate-billing-contact-dismiss" href="'
		       . esc_url( BillingContactActions::dismissUrl( $reason ) ) . '">' . $dismiss . '</a></p>'
		       . '</div>';
	}

	private function body( $reason ) {
		if ( BillingContactState::REASON_UNREACHABLE === $reason ) {
			return esc_html__(
				"Our emails to the person who manages translation for this site bounced or were reported as spam, so we can't reach them. If a payment issue ever pauses translation, we can't tell anyone — and your site quietly stops getting translated.",
				'sitepress'
			);
		}

		return esc_html__(
			"We don't have the name and email of the person who manages translation for this site. If a payment issue ever pauses translation, we can't tell anyone — and your site quietly stops getting translated.",
			'sitepress'
		);
	}

	public static function settingsUrl() {
		return admin_url( 'admin.php?page=tm/menu/settings&section=ai-translation' );
	}
}
