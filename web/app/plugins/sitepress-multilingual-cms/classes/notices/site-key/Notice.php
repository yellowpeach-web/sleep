<?php

namespace WPML\Notices\SiteKey;

use WPML\Core\WP\App\Resources;
use WPML\LIB\WP\User;
use WPML\Notices\BlockEditorNotice;
use WPML\TM\ATE\ClonedSites\AutoMigration\Handler as AutoMigrationHandler;
use WPML\TM\ATE\ClonedSites\ReconnectState;
use WPML\UIPage;

class Notice implements \IWPML_Action, \IWPML_Backend_Action {
	const NOTICE_ID             = 'wpml-site-key-notice-regular';
	const NOTICE_ID_TRANSLATION = 'wpml-site-key-notice-translation';
	const NOTICE_GROUP          = 'wpml-site-key-notices';

	const BLOCK_EDITOR_NOTICE_ID = 'wpml-site-key-notice';

	private $notices;

	private $siteKeyDefined = null;

	public function __construct( \WPML_Notices $notices ) {
		$this->notices = $notices;
	}

	public function add_hooks() {
		add_action( 'admin_init', [ $this, 'addNotice' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueScripts' ] );
	}

	public function enqueueScripts() {
		if ( $this->isSuppressedByAutoMigration() ) {
			return;
		}

		if ( $this->isSiteKeyDefined() ) {
			return;
		}

		if ( self::isBlockEditorScreen() ) {
			BlockEditorNotice::enqueue( $this->getBlockEditorNotice() );

			return;
		}

		$fn = Resources::enqueueApp( 'notices-site-key' );
		$fn( $this->getData() );
	}

	private function getBlockEditorNotice() {
		return [
			'id'            => self::BLOCK_EDITOR_NOTICE_ID,
			'status'        => 'error',
			/* translators: Title of the notice saying the site needs a new key from wpml.org, and the text of the link that opens the screen where the key is entered. */
			'text'          => __( 'A New Site Key Is Required', 'sitepress' ) . ' '
							   . __( 'To continue using WPML features like automatic translation and plugin updates, please register your site again. Don\'t worry, your existing content and translations are safe.', 'sitepress' ),
			'actions'       => [
				[
					/* translators: Button label in the site key notice: hand the key to WPML so the site is known. Verb, imperative. */
					'label' => __( 'Register', 'sitepress' ),
					'url'   => admin_url( 'admin.php?page=' . WPML_PLUGIN_FOLDER . '/menu/languages.php' ),
				],
			],
			'isDismissible' => true,
		];
	}

	private static function isBlockEditorScreen() {
		return function_exists( 'get_current_screen' )
			   && class_exists( 'WPML_Block_Editor_Helper' )
			   && \WPML_Block_Editor_Helper::is_edit_post();
	}

	private function getData() {
		return [
			'name' => 'wpml_site_key_notice',
			'data' => [
				'nonce'   => wp_create_nonce( 'save_site_key_wpml' ),
				'siteUrl' => RegistrationSiteUrl::get(),
			],
		];
	}

	public function addNotice() {
		if ( $this->isSuppressedByAutoMigration() ) {
			$this->notices->remove_notice( self::NOTICE_GROUP, self::NOTICE_ID );
			$this->notices->remove_notice( self::NOTICE_GROUP, self::NOTICE_ID_TRANSLATION );
			return;
		}

		if ( ! $this->isSiteKeyDefined() ) {
			$regularNotice = $this->createNotice( self::NOTICE_ID, true );
			$regularNotice->add_display_callback( [ self::class, 'shouldDisplayRegularNotice' ] );
			$regularNotice->add_user_restriction( User::getCurrentId() );
			$this->notices->add_notice( $regularNotice );

			$translationNotice = $this->createNotice( self::NOTICE_ID_TRANSLATION, false );
			$translationNotice->add_display_callback( [ self::class, 'shouldDisplayTranslationNotice' ] );
			$this->notices->add_notice( $translationNotice );
		} else {
			$this->notices->remove_notice( self::NOTICE_GROUP, self::NOTICE_ID );
			$this->notices->remove_notice( self::NOTICE_GROUP, self::NOTICE_ID_TRANSLATION );
		}
	}

	private function isSuppressedByAutoMigration(): bool {
		return AutoMigrationHandler::getMigrationData() !== null
		       || ( ReconnectState::isReconnecting() && ! ReconnectState::isNoAnswer() );
	}

	private function isSiteKeyDefined(): bool {
		if ( ! function_exists( 'OTGS_Installer' ) ) {
			return true;
		}

		if ( null === $this->siteKeyDefined ) {
			$this->siteKeyDefined = (bool) \OTGS_Installer()->get_site_key( 'wpml' ) || $this->isSiteKeyStored();
		}

		return $this->siteKeyDefined;
	}

	private function isSiteKeyStored(): bool {
		if ( '' === StoredSiteKey::read() ) {
			return false;
		}

		StoredSiteKey::forgetCachedCopies();

		return true;
	}

	private function createNotice( $noticeId, $allowDismiss ) {
		$notice = $this->notices->create_notice(
			$noticeId,
			$this->getNoticeText(),
			self::NOTICE_GROUP
		);

		$notice->set_dismissible( $allowDismiss );
		$notice->set_css_class_types( 'error' );

		return $notice;
	}

	private function getNoticeText(): string {
		$notice_id   = 'wpml-site-key-notice-wpml-site-key-notice-translation';
		$site_url    = RegistrationSiteUrl::get();
		$account_url = \WPML\OutboundLinks\OutboundLinks::to(
			'https://app.wpml.org/account/sites?add=%s',
			array(
				'medium'   => 'notice',
				'campaign' => 'account',
			)
		);

		return sprintf(
			'<div id="%s" class="wpml-site-key-notice-container" data-notice-id="wpml-site-key-notice-translation" style="margin-top: 5px;">' .
			'<h2>%s</h2>' .
			'<p>%s</p>' .
			'<form class="wpml-register-form">' .
			'<label for="wpml_site_key">%s</label>' .
			'<div class="wpml-register-form__input">' .
			'<input type="text" size="20" name="wpml_site_key" id="wpml_site_key" class="" placeholder="%s" value="">' .
			'<button type="submit" class="button-secondary" disabled="">%s</button>' .
			'</div>' .
			'</form>' .
			'<div class="wpml-notice-help">' .
			'<a href="' . $account_url . '" target="_blank" rel="noopener noreferrer">%s</a>' .
			'</div>' .
			'</div>',
			esc_attr( $notice_id ),
			/* translators: Title of the notice saying the site needs a new key from wpml.org, and the text of the link that opens the screen where the key is entered. */
			esc_html__( 'A New Site Key Is Required', 'sitepress' ),
			esc_html__( 'To continue using WPML features like automatic translation and plugin updates, please register your site again. Don\'t worry, your existing content and translations are safe.', 'sitepress' ),
			/* translators: Label in front of the field where the key from wpml.org is typed. */
			esc_html__( 'Site key:', 'sitepress' ),
			esc_attr__( 'Enter your site key here', 'sitepress' ),
			/* translators: Button label in the site key notice: hand the key to WPML so the site is known. Verb, imperative. */
			esc_html__( 'Register', 'sitepress' ),
			esc_url( $site_url ),
			esc_html__( 'Get a key for this site', 'sitepress' )
		);
	}

	private static function isWpmlPageResponsibleForTranslation(): bool {
		return UIPage::isTranslationManagement( $_GET ) || UIPage::isTranslationQueue( $_GET );
	}

	private static function isPageWhereTheKeyIsEntered(): bool {
		return isset( $GLOBALS['plugin_page'] ) && 'wpml-activate-update' === $GLOBALS['plugin_page'];
	}

	private static function isPageThatKeepsTheNotice(): bool {
		return self::isWpmlPageResponsibleForTranslation() || self::isPageWhereTheKeyIsEntered();
	}

	public static function shouldDisplayRegularNotice() {
		return ! self::isBlockEditorScreen() && ! self::isPageThatKeepsTheNotice();
	}

	public static function shouldDisplayTranslationNotice() {
		return ! self::isBlockEditorScreen() && self::isPageThatKeepsTheNotice();
	}
}
