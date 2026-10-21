<?php

namespace WPML\WPSEO\Shared;

class HeadLangsNoticeHooks implements \IWPML_Backend_Action {

	const SCREEN_IDS = [ 'dashboard', 'plugins' ];

	public function add_hooks() {
		add_action( 'current_screen', [ $this, 'addNoticeInAdminLanguage' ] );
	}

	public function addNoticeInAdminLanguage( $screen ) {
		if ( ! self::isNoticeScreen( $screen ) ) {
			return;
		}

		$notices = wpml_get_admin_notices();

		if ( ! self::isNoticePending( $notices ) ) {
			return;
		}

		$notice = self::makeNotice();

		if ( ! $notices->is_notice_dismissed( $notice ) ) {
			$notices->add_notice( $notice );
		}
	}

	private static function isNoticeScreen( $screen ) {
		return in_array( $screen->id, self::SCREEN_IDS, true );
	}

	private static function isNoticePending( $notices ) {
		if ( Options::get( HeadLangs::KEY_NOTICE_PENDING ) ) {
			return true;
		}

		$isStoredByEarlierVersion = (bool) $notices->get_notice( HeadLangs::NOTICE_ID );
		if ( $isStoredByEarlierVersion ) {
			Options::set( HeadLangs::KEY_NOTICE_PENDING, true );
		}

		return $isStoredByEarlierVersion;
	}

	private static function makeNotice() {
		$notice = \WPML_Notice::make( HeadLangs::NOTICE_ID, self::getNoticeText() );
		$notice->set_restrict_to_screen_ids( self::SCREEN_IDS );
		$notice->set_css_class_types( 'info' );
		$notice->set_dismissible( true );

		return $notice;
	}

	private static function getNoticeText() {
		$heading   = '<h2>' . /* translators: Heading of the notice shown on the WordPress dashboard and plugins screen after WPML stopped adding hreflang tags to the page head. "Hreflang" is an HTML attribute name and stays in English. */ esc_html__( 'Hreflang tags moved to your sitemap', 'wp-seo-multilingual' ) . '</h2>' . PHP_EOL;
		$text      = '<p>' . /* translators: Text of the "Hreflang tags moved to your sitemap" notice. "<head>" is the name of an HTML section and must stay exactly as it is. */ esc_html__( "To improve SEO and avoid duplicate hreflang data, WPML now adds hreflang tags only to your sitemap. We have disabled the hreflang tags in your page's <head> section.", 'wp-seo-multilingual' ) . '</p>' . PHP_EOL;
		$actionUrl = add_query_arg( [ 'page' => 'tm/menu/settings', 'section' => 'urls-and-seo' ], admin_url( 'admin.php' ) ) . '#lang-sec-9-5';
		$actionTag = '<a href="' . esc_url( $actionUrl ) . '">';
		/* translators: Last line of the "Hreflang tags moved to your sitemap" notice. %1$s: opening link tag, %2$s: closing link tag. The words between them are a path through the WordPress admin and should read as those screens are named in this language. "<head>" is the name of an HTML section and stays as it is. */
		$action = '<p>' . sprintf( esc_html__( 'If you prefer to keep them in the <head>, you can re-enable this option in %1$sWPML → Settings → URLs and SEO%2$s.', 'wp-seo-multilingual' ), $actionTag, '</a>' ) . '</p>' . PHP_EOL;

		return $heading . $text . $action;
	}
}
