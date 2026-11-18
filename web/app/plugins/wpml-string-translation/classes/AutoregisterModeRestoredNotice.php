<?php

namespace WPML\ST;

use WPML\Request\Adapter\AdminPost;
use WPML\Request\Policy\Authenticity;
use WPML\Request\Policy\Policy;
use WPML\StringTranslation\Application\Setting\Repository\SettingsRepositoryInterface;
use WPML\StringTranslation\Infrastructure\Setting\Repository\SettingsRepository;
use WPML\StringTranslation\Infrastructure\Setting\Repository\UrlRepository;
use WPML_WP_API;

class AutoregisterModeRestoredNotice {

	const DISMISS_ACTION = 'wpml_st_dismiss_autoregister_mode_restored_notice';

	const NOTICE_ID = 'wpml-st-autoregister-mode-restored-notice';

	const SETTINGS_URL = 'admin.php?page=tm/menu/main.php&tab=strings#dashboard_wpml_st_autoregister';

	private $settingsRepository;

	private $wpApi;

	public function __construct( SettingsRepositoryInterface $settingsRepository, WPML_WP_API $wpApi ) {
		$this->settingsRepository = $settingsRepository;
		$this->wpApi              = $wpApi;
	}

	public static function init() {
		if ( ! is_admin() ) {
			return;
		}

		global $sitepress;
		$notice = new self( new SettingsRepository( $sitepress, new UrlRepository( $sitepress ) ), new WPML_WP_API() );
		add_action( 'admin_notices', [ $notice, 'render' ] );
		add_filter( 'wpml_tm_dashboard_notices', [ $notice, 'addToTmDashboardNotices' ] );
		AdminPost::register(
			self::DISMISS_ACTION,
			Policy::capability( 'manage_options', Authenticity::actionNonce( self::DISMISS_ACTION ) ),
			[ $notice, 'onDismissRequest' ]
		);
	}

	public function addToTmDashboardNotices( $notices ) {
		$notices   = (array) $notices;
		$notices[] = [ $this, 'render' ];

		return $notices;
	}

	public function shouldDisplay(): bool {
		return current_user_can( 'manage_options' )
			&& $this->settingsRepository->hasKeyInSettings( SettingsRepositoryInterface::AUTOREGISTER_MODE_RESTORED_NOTICE_KEY )
			&& ( $this->wpApi->is_string_translation_page() || $this->wpApi->is_tm_page( 'dashboard' ) );
	}

	public function render() {
		if ( ! $this->shouldDisplay() ) {
			return;
		}

		$dismissUrl = wp_nonce_url(
			add_query_arg( 'action', self::DISMISS_ACTION, admin_url( 'admin-post.php' ) ),
			self::DISMISS_ACTION
		);

		$text = sprintf(
			/* translators: Notice on the WPML → Translation screens after the update switched string detection back on. %1$s: opening link tag, %2$s: closing link tag; the words between them become a link to the String Translation settings. */
			esc_html__( "An earlier WPML version switched off the detection of untranslated strings on this site by itself. WPML 5.3 turned it back on to the recommended mode, 'strings I encounter while logged in'. If you had turned it off on purpose, set it back in %1\$sString Translation settings%2\$s.", 'wpml-string-translation' ),
			'<a href="' . esc_url( admin_url( self::SETTINGS_URL ) ) . '">',
			'</a>'
		);

		echo '<div id="' . esc_attr( self::NOTICE_ID ) . '" class="notice notice-info ' . esc_attr( self::NOTICE_ID ) . '" style="position:relative;padding-right:38px">';
		echo '<h3>' . esc_html__( 'WPML turned string detection back on', 'wpml-string-translation' ) . '</h3>';
		echo '<p>' . wp_kses( $text, [ 'a' => [ 'href' => [] ] ] ) . '</p>';
		echo '<a class="notice-dismiss" style="text-decoration:none" href="' . esc_url( $dismissUrl ) . '"><span class="screen-reader-text">'
			. esc_html__( 'Dismiss this notice.' )
			. '</span></a>';
		echo '</div>';
	}

	public function dismiss(): bool {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$this->settingsRepository->removeKeyFromSettings( SettingsRepositoryInterface::AUTOREGISTER_MODE_RESTORED_NOTICE_KEY );

		return true;
	}

	public function onDismissRequest() {
		check_admin_referer( self::DISMISS_ACTION );
		$this->dismiss();
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=tm/menu/main.php' ) );
		exit;
	}
}
