<?php

namespace WPML\Setup;

use WPML\SuperGlobals\Request;

class FunnelRedirect implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	const SETUP_PAGE = WPML_PLUGIN_FOLDER . '/menu/setup.php';

	const DASHBOARD_PAGE = 'tm/menu/main.php';

	const LANGUAGES_PAGE    = 'tm/menu/settings';
	const LANGUAGES_SECTION = 'languages';

	private $sitepress;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		add_action( 'admin_init', [ $this, 'maybe_redirect' ], 1 );
	}

	public function maybe_redirect() {
		$method = isset( $_SERVER['REQUEST_METHOD'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
			: '';

		if ( wp_doing_ajax() ) {
			return;
		}

		if ( is_network_admin() ) {
			return;
		}

		global $pagenow;
		if ( 'admin.php' !== $pagenow ) {
			return;
		}

		$target = $this->target_for_request( $method, Request::page() );
		if ( null === $target ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . $target ) );
		exit;
	}

	private function target_for_request( $method, $page ) {
		if ( 'GET' !== strtoupper( $method ) && self::SETUP_PAGE !== $page ) {
			return null;
		}

		return $this->target_for( $page );
	}

	private function target_for( $page ) {
		if ( ! $this->sitepress->is_setup_complete() ) {
			return null;
		}

		$needs_a_language = 2 > count( $this->sitepress->get_active_languages() );

		if ( self::SETUP_PAGE === $page ) {
			return $needs_a_language ? $this->languages_target() : self::DASHBOARD_PAGE;
		}

		if ( $needs_a_language && $this->needs_a_configured_site( $page ) && ! $this->is_languages_screen( $page ) ) {
			return $this->languages_target();
		}

		return null;
	}

	private function languages_target() {
		return self::LANGUAGES_PAGE . '&section=' . self::LANGUAGES_SECTION;
	}

	private function is_languages_screen( $page ) {
		return self::LANGUAGES_PAGE === $page
			&& self::LANGUAGES_SECTION === Request::param( 'section' );
	}

	private function needs_a_configured_site( $page ) {
		if ( ! $this->is_wpml_page( $page ) ) {
			return false;
		}

		$always_reachable = [
			WPML_PLUGIN_FOLDER . '/menu/support.php',
			WPML_PLUGIN_FOLDER . '/menu/troubleshooting.php',
			WPML_PLUGIN_FOLDER . '/menu/debug-information.php',
			WPML_PLUGIN_FOLDER . '/menu/network.php',
			'wpml-activate-update',
		];

		return ! in_array( $page, $always_reachable, true );
	}

	private function is_wpml_page( $page ) {
		if ( ! $page ) {
			return false;
		}

		foreach ( [ WPML_PLUGIN_FOLDER . '/menu/', 'tm/menu/', 'wpml-' ] as $prefix ) {
			if ( strpos( $page, $prefix ) === 0 ) {
				return true;
			}
		}

		return false;
	}
}
