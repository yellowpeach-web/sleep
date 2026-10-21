<?php

namespace ACFML\Notice;

use ACFML\Helper\BoldNames;
use ACFML\Helper\FieldGroup;
use ACFML\Tools\AdminUrl;
use WPML\LIB\WP\Hooks;

class ActivationNotice implements \IWPML_Backend_Action {

	const NOTICE_PRIORITY_BEFORE_WPML_PROCESS = 9;
	const NOTICE_GROUP                        = 'acfml';
	const NOTICE_ID                           = 'acfml-activation-notice';

	public function add_hooks() {
		Hooks::onAction( 'current_screen', self::NOTICE_PRIORITY_BEFORE_WPML_PROCESS )
			->then( [ $this, 'maybeShowNotice' ] );
	}

	public function maybeShowNotice() {
		$pending = get_option( Activation::OPTION_PENDING );

		if ( ! $pending || ! function_exists( 'wpml_get_admin_notices' ) ) {
			return;
		}

		$notices = wpml_get_admin_notices();

		if ( ! FieldGroup::hasGroupMissingMode() ) {
			$this->removeNotice( $notices );
			delete_option( Activation::OPTION_PENDING );
			return;
		}

		if ( FieldGroup::isListScreen() ) {
			$this->removeNotice( $notices );
			return;
		}

		$resetDismiss = ( Activation::PENDING_RESET === $pending );
		if ( $resetDismiss ) {
			update_option( Activation::OPTION_PENDING, true );
		}

		$this->createNotice( $notices, $resetDismiss );
	}

	private function createNotice( $notices, $resetDismiss = false ) {
		/* translators: Heading of the admin notice shown after the plugin is switched on. Keep the bold tags around the panel's name. Verb phrase, imperative. */
		$text  = '<h2>' . BoldNames::render( __( 'Finish the ACF <b>Multilingual Setup</b>', 'acfml' ) ) . '</h2>';
		/* translators: Body of that admin notice; "Field Group" is ACF's own name for the screen. */
		$text .= '<p>' . esc_html__( 'Before you can start translating, you need to edit each ACF Field Group to set a translation option for the fields inside it.', 'acfml' ) . '</p>';
		$text .= '<p>' . sprintf(
			/* translators: The placeholders are replaced by an HTML link pointing to the documentation. */
			esc_html__( 'Read more about %1$show to translate your ACF custom fields%2$s', 'acfml' ),
			'<a href="' . esc_url( Links::getAcfmlMainDoc() ) . '" class="wpml-external-link" target="_blank">',
			'</a>'
		) . '</p>';

		$text .= sprintf(
			/* translators: The placeholders are replaced by an HTML link pointing to field groups list. */
			esc_html__( '%1$sSet translation options%2$s', 'acfml' ),
			'<a href="' . esc_url( AdminUrl::getFieldGroupsList() ) . '" class="button">',
			'</a>'
		);

		$notice = $notices->create_notice( self::NOTICE_ID, $text, self::NOTICE_GROUP );
		$notice->set_hideable( true );
		if ( $resetDismiss ) {
			$notice->reset_dismiss();
		}
		$notice->set_css_class_types( [ 'info' ] );
		$notices->add_notice( $notice );
	}

	private function removeNotice( $notices ) {
		$notices->remove_notice( self::NOTICE_GROUP, self::NOTICE_ID );
	}
}
