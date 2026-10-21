<?php

namespace ACFML\Notice;

use ACFML\FieldGroup\PreflightHooks;
use ACFML\FieldGroup\UnconfiguredGroups;
use ACFML\Helper\FieldGroup;
use WPML\LIB\WP\Hooks;

class FieldGroupModes implements \IWPML_Backend_Action {

	const NOTICE_PRIORITY = 9;
	const NOTICE_SCREEN   = 'edit-acf-field-group';
	const NOTICE_GROUP    = 'acfml';
	const NOTICE_ID       = 'field-group-modes';

	public function add_hooks() {
		if ( ! wp_doing_ajax() ) {
			Hooks::onAction( 'admin_notices', self::NOTICE_PRIORITY )
				->then( [ $this, 'onFieldGroupsListNotice' ] );
		}
	}

	public function onFieldGroupsListNotice() {
		if (
			! function_exists( 'wpml_get_admin_notices' )
			|| ! function_exists( 'get_current_screen' )
			|| ! \WPML_ACF::is_acf_active()
		) {
			return;
		}

		$screen = get_current_screen();
		if ( self::NOTICE_SCREEN !== $screen->id ) {
			return;
		}

		if ( FieldGroup::hasGroupMissingMode() ) {
			$this->createNotice( wpml_get_admin_notices() );
		} else {
			$this->removeNotice( wpml_get_admin_notices() );
		}
	}

	private function createNotice( $notices ) {
		/* translators: Heading of the admin notice inviting the site owner to set a translation option on each field group. */
		$text  = ' <h2>' . esc_html__( "Let's Start Translating!", 'acfml' ) . '</h2>';
		/* translators: Body of that admin notice; "Field Group" is ACF's own name for the screen. */
		$text .= '<p>' . esc_html__( "Edit each Field Group to select a translation option for the fields inside it. If you don't set a translation option, you will not be able to translate your fields.", 'acfml' ) . '</p>';
		$text .= '<p>' . sprintf(
			/* translators: The placeholders are replaced by an HTML link pointing to the documentation. */
			esc_html__( 'Read more about %1$show to translate your ACF custom fields%2$s', 'acfml' ),
			'<a href="' . esc_url( Links::getAcfmlMainDoc() ) . '" class="wpml-external-link" target="_blank">',
			'</a>'
		) . '</p>';

		$inventory = UnconfiguredGroups::get();
		if ( $inventory['groupCount'] > 0 ) {
			$text .= '<p>' . PreflightHooks::getMessage( $inventory ) . '</p>';
		}

		$notice = $notices->create_notice( self::NOTICE_ID, $text, self::NOTICE_GROUP );
		$notice->set_hideable( true );
		$notice->set_css_class_types( [ 'info' ] );
		$notice->set_restrict_to_screen_ids( [ self::NOTICE_SCREEN ] );

		if ( $inventory['groupCount'] > 0 ) {
			$notice->add_action(
				new \WPML_Notice_Action(
					PreflightHooks::getApplyLabel( $inventory['groupCount'] ),
					esc_url( PreflightHooks::getApplyUrl() ),
					false,
					false,
					true
				)
			);
		}

		$notices->add_notice( $notice );
	}

	private function removeNotice( $notices ) {
		$notices->remove_notice( self::NOTICE_GROUP, self::NOTICE_ID );
	}
}
