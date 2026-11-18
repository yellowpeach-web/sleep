<?php

namespace WPML\UrlHandling\SubsiteClash;

use WPML\LIB\WP\User;
use WPML\SuperGlobals\Request;
use WPML\UIPage;

class ClashNotice implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	const URLS_AND_SEO_SECTION = 'urls-and-seo';

	const SETTINGS_LINKS_CAPABILITY = 'wpml_manage_languages';

	public function add_hooks() {
		if ( ! is_multisite() ) {
			return;
		}

		add_action( 'all_admin_notices', [ $this, 'renderSiteNotice' ] );

		if ( ! is_main_site() ) {
			return;
		}

		add_action( 'network_admin_notices', [ $this, 'renderNetworkNotice' ] );
		add_action( 'network_site_new_form', [ $this, 'renderAddSiteHint' ] );
	}

	public function renderSiteNotice() {
		if ( ! $this->isSiteNoticeScreen() || ! User::canManageTranslations() ) {
			return;
		}

		$clashes = is_main_site() ? Detector::clashes() : [];
		if ( $clashes ) {
			NoticeMarkup::printSiteNotice( $clashes, is_super_admin(), Detector::defaultFixDirectoryCode(), User::hasCap( self::SETTINGS_LINKS_CAPABILITY ), Detector::state()->path() );

			return;
		}

		$hostClashes = Detector::hostClashes();
		if ( $hostClashes ) {
			NoticeMarkup::printHostSiteNotice( $hostClashes, is_super_admin(), User::hasCap( self::SETTINGS_LINKS_CAPABILITY ) );
		}
	}

	public function renderNetworkNotice() {
		if ( ! is_network_admin() || ! $this->isNetworkNoticeScreen() || ! is_super_admin() ) {
			return;
		}

		$focusSiteId = 'site-info.php' === $GLOBALS['pagenow'] && isset( $GLOBALS['id'] ) && is_int( $GLOBALS['id'] ) ? $GLOBALS['id'] : 0;

		$clashes = Detector::clashes();
		if ( $clashes ) {
			NoticeMarkup::printNetworkNotice( $clashes, (string) get_option( 'blogname' ), Detector::defaultFixDirectoryCode(), Detector::state()->path(), $focusSiteId );

			return;
		}

		$hostClashes = Detector::hostClashes();
		if ( $hostClashes ) {
			NoticeMarkup::printHostNetworkNotice( $hostClashes, (string) get_option( 'blogname' ), $focusSiteId );
		}
	}

	public function renderAddSiteHint() {
		if ( is_subdomain_install() ) {
			NoticeMarkup::printAddSiteHostHint( Detector::hostsAddSiteCanCreate() );

			return;
		}

		$codes = Detector::reservedCodes();
		if ( ! $codes ) {
			return;
		}

		NoticeMarkup::printAddSiteHint( $codes );
	}

	private function isNetworkNoticeScreen(): bool {
		return isset( $GLOBALS['pagenow'] ) && in_array( $GLOBALS['pagenow'], [ 'sites.php', 'site-new.php', 'site-info.php' ], true );
	}

	private function isSiteNoticeScreen(): bool {
		if ( is_network_admin() || is_user_admin() ) {
			return false;
		}

		if ( isset( $GLOBALS['pagenow'] ) && 'index.php' === $GLOBALS['pagenow'] && ! Request::page() ) {
			return true;
		}

		if ( ! UIPage::isWpmlAdminScreen() || ! self::isInWpmlMenu() ) {
			return false;
		}

		return ! self::isUrlsAndSeo();
	}

	private static function isInWpmlMenu(): bool {
		$parent = isset( $GLOBALS['parent_file'] ) && is_string( $GLOBALS['parent_file'] ) ? $GLOBALS['parent_file'] : '';

		return '' !== $parent && UIPage::isWpmlAdminScreen( [ 'page' => $parent ] );
	}

	public static function isUrlsAndSeo(): bool {
		return UIPage::isSettings( [ 'page' => Request::page() ] ) && self::URLS_AND_SEO_SECTION === Request::param( 'section' );
	}
}
