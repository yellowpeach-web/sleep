<?php

namespace WPML\TM\ATE\ClonedSites;

use WPML\API\Settings;
use WPML\LIB\WP\User;
use WPML\Setup\Option;
use WPML\UIPage;

class TranslateEverythingOnNewRecord implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	const NOTICE_ID    = 'translate-everything-off-on-new-record';
	const NOTICE_GROUP = 'wpml-ate-cloned-sites';
	const PENDING      = TranslateEverythingBackOn::PENDING;
	const REMOVAL      = TranslateEverythingBackOn::REMOVAL;

	public static function switchOff() {
		if ( ! Option::shouldTranslateEverything() ) {
			return;
		}

		Option::setTranslateEverything( false );
		Settings::setAndSave( self::PENDING, true );
	}

	public function add_hooks() {
		add_action( 'admin_init', [ $this, 'queueNotice' ] );
		add_action( 'admin_init', [ $this, 'removeNotice' ] );
		add_filter( 'wpml_tm_dashboard_notices', [ $this, 'addDashboardNotice' ] );
	}

	public function queueNotice() {
		if ( ! Settings::get( self::PENDING ) || ! User::canManageTranslations() ) {
			return;
		}

		$notices = wpml_get_admin_notices();
		$notices->add_notice( self::notice( $notices ) );
		Settings::setAndSave( self::PENDING, false );
	}

	public function addDashboardNotice( $notices ) {
		$stored = self::storedNotice();
		if ( ! $stored || wpml_get_admin_notices()->is_notice_dismissed( $stored ) ) {
			return $notices;
		}

		$notices   = is_array( $notices ) ? $notices : [];
		$notices[] = function () {
			echo '<div class="notice notice-warning" id="' . esc_attr( self::NOTICE_ID ) . '-dashboard"><p>'
				. esc_html(
					/* translators: Notice on the Translation Management page after WPML switched off Translate Everything on a copied or moved site; the setting is further down that page. */
					__( 'WPML switched off Translate Everything on this site, because the site now has its own translation record (it was copied or moved). If this is your live site and you want automatic translation, turn Translate Everything on again below.', 'sitepress' )
				)
				. '</p></div>';
		};

		return $notices;
	}

	public function removeNotice() {
		if ( ! Settings::get( self::REMOVAL ) || ! User::canManageTranslations() ) {
			return;
		}

		if ( self::storedNotice() ) {
			wpml_get_admin_notices()->remove_notice( self::NOTICE_GROUP, self::NOTICE_ID );
		}
		Settings::setAndSave( self::REMOVAL, false );
	}

	private static function storedNotice() {
		return wpml_get_admin_notices()->get_notice( self::NOTICE_ID, self::NOTICE_GROUP );
	}

	private static function notice( \WPML_Notices $notices ): \WPML_Notice {
		$text = sprintf(
			/* translators: Admin notice after WPML switched off Translate Everything on a copied or moved site. %1$s: opening link tag to the Translation Management screen, %2$s: closing link tag. */
			__( 'WPML switched off Translate Everything on this site, because the site now has its own translation record (it was copied or moved). If this is your live site and you want automatic translation, turn Translate Everything on again in %1$sWPML → Translation Management%2$s.', 'sitepress' ),
			'<a href="' . esc_url( admin_url( UIPage::getTMDashboard() ) ) . '">',
			'</a>'
		);

		$notice = $notices->create_notice( self::NOTICE_ID, $text, self::NOTICE_GROUP );
		$notice->set_css_class_types( [ 'warning' ] );
		$notice->set_dismissible( true );
		$notice->reset_dismiss();
		$notice->add_capability_check( [ 'manage_options', 'manage_translations' ] );
		$notice->add_exclude_from_page( UIPage::TM_PAGE );

		return $notice;
	}
}
