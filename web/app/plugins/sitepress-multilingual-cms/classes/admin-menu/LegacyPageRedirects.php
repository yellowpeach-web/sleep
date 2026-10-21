<?php

use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations\AdminTextsTranslationController;

class WPML_Legacy_Page_Redirects implements IWPML_Backend_Action {

	const PLUGIN_FOLDER = 'sitepress-multilingual-cms';
	const TM_OLD_FOLDER = 'wpml-translation-management';
	const TM_FOLDER     = 'tm';
	const ST_FOLDER     = 'wpml-string-translation';
	const PACKAGES_SLUG = 'wpml-package-management';

	const DUPLICATE_DOORS = array(
		'wpml-tm-ate-log'        => 'ate-error-logs',
		'wpml-tm-job-log'        => 'job-logs',
		'otgs-installer-support' => 'system-check',
		self::PACKAGES_SLUG      => 'packages',
	);

	const RETIRED_TROUBLESHOOTING_PAGE = self::PLUGIN_FOLDER . '/menu/troubleshooting.php';

	const TRANSLATORS_NOTIFICATIONS = 'admin.php?page=' . self::TM_FOLDER
		. '/menu/settings&section=translators&flash=translation-notifications';

	const RESCUE_PRIORITY = PHP_INT_MAX;

	public function add_hooks() {
		add_action( 'admin_init', array( $this, 'maybe_redirect' ) );
		add_action( 'admin_menu', array( $this, 'rescue_unregistered_page' ), self::RESCUE_PRIORITY );
	}

	/**
	 * A legacy slug that NOTHING registered in the current licence shape must not
	 * die on WordPress's access check (wpmldev-8292).
	 *
	 * `wp-admin/admin.php` requires `wp-admin/menu.php` — whose tail runs
	 * `user_can_access_admin_page()` and `wp_die( …, 403 )` for a slug with no
	 * page hook — BEFORE it fires `admin_init`, so `maybe_redirect()` above only
	 * ever sees a slug some module registered. The legacy Settings slug is
	 * registered only without Translation Management
	 * (`WPML_Main_Admin_Menu::configure()`), so on a full licence the URL WPML
	 * still emits (`WPML_Admin_URL::multilingual_setup()`,
	 * `WPML_API_Hook_Links::get_post_translation_settings_link()`) answered 403;
	 * `wpml-media`, `string-translation.php` and `wpml-package-management`
	 * without their add-on, and the queue without TM, died the same way; so did
	 * the pre-5.0 plugins' own page paths (`wpml-translation-management/menu/
	 * translation-options.php`, `wpml-package-management/menu/
	 * package-management.php`), which nothing registers at all (round-8 A2-F2).
	 *
	 * Runs on `admin_menu` at the latest priority: after every registration,
	 * before the check. Touches ONLY a slug with no page by then — a registered
	 * legacy page keeps rendering (a blog licence's Settings page), and the
	 * `admin_init` pass keeps its behaviour for it. The redirect needs the
	 * capability the TARGET page is registered with, and goes through
	 * `wp_safe_redirect()`.
	 *
	 * @return void
	 */
	public function rescue_unregistered_page() {
		if ( wp_doing_ajax() ) {
			return;
		}

		global $pagenow;
		if ( 'admin.php' !== $pagenow ) {
			return;
		}

		$page = \WPML\SuperGlobals\Request::page();

		if ( '' === $page || $this->is_registered_page( $page ) ) {
			return;
		}

		if ( self::RETIRED_TROUBLESHOOTING_PAGE === $page
			&& '' !== \WPML\Troubleshooting\Actions\Dispatcher::requested( 'debug_action' ) ) {
			$this->keep_alive_for_dispatcher( $page );
			return;
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'GET' !== strtoupper( $method ) ) {
			return;
		}

		$target = $this->resolve_target(
			$page,
			\WPML\SuperGlobals\Request::param( 'sm' ),
			\WPML\SuperGlobals\Request::param( 'trop' )
		);
		if ( null === $target ) {
			$target = $this->resolve_unregistered_only_target( $page );
		}
		if ( null === $target ) {
			return;
		}

		$capability = $this->registered_capability( $this->page_of( $target ) );
		if ( null === $capability || ! current_user_can( $capability ) ) {
			return;
		}

		$this->redirect_to( $target );
	}

	private function keep_alive_for_dispatcher( $page ) {
		add_submenu_page( '', '', '', 'manage_options', $page, '__return_null' );
	}

	/**
	 * Homes for legacy slugs that are redirected ONLY when nothing registered
	 * them: while a shape registers the page (a blog licence's legacy Settings),
	 * it renders as before, so these must not join `resolve_target()`, which the
	 * `admin_init` pass applies to registered pages too.
	 *
	 * @param string $page
	 *
	 * @return string|null
	 */
	private function resolve_unregistered_only_target( $page ) {
		if ( self::PLUGIN_FOLDER . '/menu/translation-options.php' === $page
			|| self::TM_OLD_FOLDER . '/menu/translation-options.php' === $page ) {
			return 'admin.php?page=' . self::TM_FOLDER . '/menu/settings';
		}

		return null;
	}

	private function is_registered_page( $slug ) {
		return null !== $this->registered_capability( $slug );
	}

	private function registered_capability( $slug ) {
		global $menu, $submenu;

		foreach ( (array) $menu as $entry ) {
			if ( is_array( $entry ) && isset( $entry[2], $entry[1] ) && $entry[2] === $slug ) {
				return (string) $entry[1];
			}
		}

		foreach ( (array) $submenu as $entries ) {
			foreach ( (array) $entries as $entry ) {
				if ( is_array( $entry ) && isset( $entry[2], $entry[1] ) && $entry[2] === $slug ) {
					return (string) $entry[1];
				}
			}
		}

		return null;
	}

	private function page_of( $target ) {
		parse_str( (string) wp_parse_url( $target, PHP_URL_QUERY ), $params );

		return isset( $params['page'] ) ? (string) $params['page'] : '';
	}


	public function maybe_redirect() {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) !== 'GET' ) {
			return;
		}

		if ( wp_doing_ajax() ) {
			return;
		}

		global $pagenow;
		if ( 'admin.php' !== $pagenow ) {
			return;
		}

		if ( ! isset( $_GET['page'] ) ) {
			return;
		}

		$page = \WPML\SuperGlobals\Request::page();
		$sm   = \WPML\SuperGlobals\Request::param( 'sm' );
		$trop = \WPML\SuperGlobals\Request::param( 'trop' );

		$target = $this->resolve_target( $page, $sm, $trop );

		if ( $target === null ) {
			return;
		}

		$this->redirect_to( $target );
	}


	private function redirect_to( $target ) {
		$anchor = '';
		$hash   = strpos( $target, '#' );
		if ( false !== $hash ) {
			$anchor = substr( $target, $hash );
			$target = substr( $target, 0, $hash );
		}

		$extra = $_GET;
		unset( $extra['page'], $extra['sm'], $extra['trop'] );

		parse_str( (string) wp_parse_url( $target, PHP_URL_QUERY ), $target_params );
		$extra = array_diff_key( $extra, $target_params );

		$separator = ( strpos( $target, '?' ) !== false ) ? '&' : '?';
		$query     = http_build_query( $extra );
		$final     = $target . ( $query !== '' ? $separator . $query : '' ) . $anchor;

		wp_safe_redirect( admin_url( $final ) );
		exit;
	}


	private function support_tool_target( $tool ) {
		return 'admin.php?page=' . self::PLUGIN_FOLDER . '/menu/support.php&tool=' . $tool;
	}

	private function resolve_target( string $page, string $sm, string $trop ) {

		if ( self::PLUGIN_FOLDER . '/menu/taxonomy-translation.php' === $page ) {
			return 'admin.php?page=' . self::TM_FOLDER . '/menu/main.php&tab=taxonomy';
		}

		if ( self::RETIRED_TROUBLESHOOTING_PAGE === $page ) {
			return 'admin.php?page=' . self::PLUGIN_FOLDER . '/menu/support.php';
		}

		if ( isset( self::DUPLICATE_DOORS[ $page ] ) ) {
			return $this->support_tool_target( self::DUPLICATE_DOORS[ $page ] );
		}

		if ( self::PACKAGES_SLUG . '/menu/package-management.php' === $page ) {
			return $this->support_tool_target( 'packages' );
		}

		if ( self::PLUGIN_FOLDER . '/menu/theme-localization.php' === $page ) {
			return AdminTextsTranslationController::rendersLocalizationSection()
				? AdminTextsTranslationController::localizationSectionUrl()
				: 'admin.php?page=' . self::TM_FOLDER . '/menu/settings&section=compatibility';
		}

		if ( self::PLUGIN_FOLDER . '/menu/languages.php' === $page && '1' !== $trop ) {
			return 'admin.php?page=' . self::TM_FOLDER . '/menu/settings&section=languages';
		}

		if ( 'wpml-media' === $page ) {
			return 'admin.php?page=' . self::TM_FOLDER . '/menu/main.php&tab=media';
		}


		if ( self::TM_OLD_FOLDER . '/menu/main.php' === $page ) {
			return $this->main_php_target( $sm );
		}

		if ( self::TM_OLD_FOLDER . '/menu/translations-queue.php' === $page ) {
			return \WPML\TM\Menu\TranslationQueue\TranslationQueuePage::base();
		}

		if ( self::TM_OLD_FOLDER . '/menu/settings' === $page ) {
			$target = $this->settings_target( $sm );
			return null !== $target ? $target : 'admin.php?page=' . self::TM_FOLDER . '/menu/settings';
		}

		if ( self::TM_FOLDER . '/menu/main.php' === $page && $sm !== '' ) {
			return $this->main_php_target( $sm );
		}

		if ( self::TM_FOLDER . '/menu/settings' === $page && '' !== $sm ) {
			return $this->settings_target( $sm );
		}

		if ( self::TM_FOLDER . '/menu/translations-queue.php' === $page ) {
			return \WPML\TM\Menu\TranslationQueue\TranslationQueuePage::base();
		}

		if ( self::ST_FOLDER . '/menu/string-translation.php' === $page ) {
			if ( '1' === $trop ) {
				return 'admin.php?page=wpml-admin-texts-translation';
			}
			if ( isset( $_GET['troubleshooting'] ) && '1' === (string) $_GET['troubleshooting'] ) {
				return null;
			}
			return 'admin.php?page=' . self::TM_FOLDER . '/menu/main.php&tab=strings';
		}

		return null;
	}


	private function main_php_target( string $sm ) {
		switch ( $sm ) {
			case 'translators':
				return 'admin.php?page=' . self::TM_FOLDER . '/menu/settings&section=translators';
			case 'jobs':
				return 'admin.php?page=' . self::TM_FOLDER . '/menu/main.php&tab=jobs';
			case 'dashboard':
				return 'admin.php?page=' . self::TM_FOLDER . '/menu/main.php&tab=dashboard';
			case 'basket':
				return 'admin.php?page=' . self::TM_FOLDER . '/menu/main.php&tab=dashboard';
			case 'mcsetup':
				return 'admin.php?page=' . self::TM_FOLDER . '/menu/settings';
			case 'notifications':
				return self::TRANSLATORS_NOTIFICATIONS;
			case 'com-log':
				return 'admin.php?page=' . self::PLUGIN_FOLDER . '/menu/support.php&tool=communication-log';
			case '':
				return 'admin.php?page=' . self::TM_FOLDER . '/menu/main.php';
		}
		return null;
	}


	private function settings_target( string $sm ) {
		switch ( $sm ) {
			case 'notifications':
				return self::TRANSLATORS_NOTIFICATIONS;
		}

		return null;
	}
}
