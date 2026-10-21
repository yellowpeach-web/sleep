<?php

class WPML_Hide_Legacy_Top_Level_Menu implements IWPML_Backend_Action {

	const HIDDEN_PARENT_SLUG = 'wpml-hidden-legacy-menu';

	const TRANSLATIONS_PAGE_SLUG = 'tm/menu/main.php';

	const SETTINGS_PAGE_SLUG = 'tm/menu/settings';

	const ADD_ONS_LABEL_SLUG  = 'wpml-add-ons-group';
	const ADD_ONS_LABEL_ORDER = 999;
	const ST_GRANT        = 'wpml_manage_string_translation';
	const STRINGS_TAB_URL = 'admin.php?page=tm/menu/main.php&tab=strings';

	private $hidden_slugs = array(
		'tm/menu/translations-queue.php',
		WPML_PLUGIN_FOLDER . '/menu/menu-sync/menus-sync.php',
		WPML_PLUGIN_FOLDER . '/menu/theme-localization.php',
		WPML_PLUGIN_FOLDER . '/menu/taxonomy-translation.php',
		WPML_PLUGIN_FOLDER . '/menu/languages.php',
		// registers it only when `! is_tm_active()` — i.e. on a blog license,
		WPML_PLUGIN_FOLDER . '/menu/translation-options.php',
		'wpml-string-translation/menu/string-translation.php',
		'wpml-package-management',
		'wpml-media',
	);

	private $hidden_slug_homes = array();

	private $current_slug_home = null;

	private $reused_slugs = array(
		'tm/menu/main.php',
		'tm/menu/settings',
		WPML_PLUGIN_FOLDER . '/menu/support.php',
	);

	public function __construct() {
		$this->hidden_slug_homes = array(
			'tm/menu/translations-queue.php'                        => self::TRANSLATIONS_PAGE_SLUG,
			WPML_PLUGIN_FOLDER . '/menu/menu-sync/menus-sync.php'   => self::TRANSLATIONS_PAGE_SLUG,
			WPML_PLUGIN_FOLDER . '/menu/theme-localization.php'     => self::SETTINGS_PAGE_SLUG,
			WPML_PLUGIN_FOLDER . '/menu/taxonomy-translation.php'   => self::TRANSLATIONS_PAGE_SLUG,
			WPML_PLUGIN_FOLDER . '/menu/languages.php'              => self::SETTINGS_PAGE_SLUG,
			WPML_PLUGIN_FOLDER . '/menu/translation-options.php'    => self::SETTINGS_PAGE_SLUG,
			'wpml-string-translation/menu/string-translation.php'   => self::TRANSLATIONS_PAGE_SLUG,
			'wpml-package-management'                               => self::TRANSLATIONS_PAGE_SLUG,
			'wpml-media'                                            => self::TRANSLATIONS_PAGE_SLUG,
		);
	}

	public function add_hooks() {
		add_filter( 'wpml_menu_item_before_build', array( $this, 'reroute_hidden_items' ), 10, 2 );
		$this->add_hidden_slug_highlight_hooks();
		add_action( 'admin_init', array( $this, 'bounce_add_ons_label_request' ) );
		add_action( 'admin_menu', array( $this, 'remove_root_duplicate_submenu' ), 999 );
		add_action( 'admin_menu', array( $this, 'lower_root_cap_for_translators' ), 999 );
		add_action( 'admin_init', array( $this, 'readd_translations_submenu_for_translators' ) );
		add_action( 'admin_menu', array( $this, 'lower_root_cap_for_string_translation_managers' ), 999 );
		add_action( 'admin_init', array( $this, 'readd_strings_submenu_for_string_translation_managers' ) );
		add_filter( 'parent_file', array( $this, 'highlight_strings_entry_for_string_translation_managers' ) );
		add_filter( 'submenu_file', array( $this, 'highlight_strings_entry_for_string_translation_managers' ) );
		add_action( 'wpml_admin_menu_configure', array( $this, 'register_add_ons_group_label' ) );
		add_action( 'admin_head', array( $this, 'print_add_ons_group_style' ) );
	}


	private function add_hidden_slug_highlight_hooks() {
		$page = \WPML\SuperGlobals\Request::page();

		if ( ! isset( $this->hidden_slug_homes[ $page ] ) ) {
			return;
		}

		$this->current_slug_home = $this->hidden_slug_homes[ $page ];

		add_filter( 'parent_file', array( $this, 'highlight_wpml_top_level' ) );
		add_filter( 'submenu_file', array( $this, 'highlight_absorbing_tab' ) );
	}

	public function highlight_wpml_top_level( $parent_file ) {
		if ( null === $this->current_slug_home ) {
			return $parent_file;
		}

		if ( ! isset( $GLOBALS['_wp_real_parent_file'] ) || ! is_array( $GLOBALS['_wp_real_parent_file'] ) ) {
			$GLOBALS['_wp_real_parent_file'] = array();
		}
		$GLOBALS['_wp_real_parent_file'][ self::HIDDEN_PARENT_SLUG ] = self::TRANSLATIONS_PAGE_SLUG;

		return self::TRANSLATIONS_PAGE_SLUG;
	}

	public function highlight_absorbing_tab( $submenu_file ) {
		return null === $this->current_slug_home ? $submenu_file : $this->current_slug_home;
	}

	public function bounce_add_ons_label_request() {
		if ( self::ADD_ONS_LABEL_SLUG !== \WPML\SuperGlobals\Request::page() ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . WPML_TM_FOLDER . '/menu/settings&section=languages' ) );
		exit;
	}

	public function lower_root_cap_for_translators() {
		if ( $this->is_string_translation_manager_only() ) {
			return;
		}
		if ( current_user_can( 'manage_translations' ) ) {
			return;
		}
		if ( current_user_can( 'wpml_manage_languages' ) ) {
			return;
		}
		if ( ! current_user_can( 'translate' ) ) {
			return;
		}

		global $menu;
		if ( ! is_array( $menu ) ) {
			return;
		}

		foreach ( $menu as $index => $item ) {
			if ( ! isset( $item[2] ) ) {
				continue;
			}
			if ( 'WPML' === ( $item[0] ?? '' ) && 'WPML' === ( $item[3] ?? '' ) ) {
				$menu[ $index ][1] = 'translate';
				return;
			}
		}
	}

	public function readd_translations_submenu_for_translators() {
		if ( $this->is_string_translation_manager_only() ) {
			return;
		}
		if ( current_user_can( 'manage_translations' ) ) {
			return;
		}
		if ( current_user_can( 'wpml_manage_languages' ) ) {
			return;
		}
		if ( ! current_user_can( 'translate' ) ) {
			return;
		}

		global $menu, $submenu;
		if ( ! is_array( $menu ) ) {
			return;
		}

		$root_slug = null;
		foreach ( $menu as $item ) {
			if ( ! isset( $item[2] ) ) {
				continue;
			}
			if ( ( $item[0] ?? '' ) === 'WPML' && ( $item[3] ?? '' ) === 'WPML' ) {
				$root_slug = (string) $item[2];
				break;
			}
		}

		if ( null === $root_slug ) {
			return;
		}

		if ( ! empty( $submenu[ $root_slug ] ) ) {
			return;
		}

		$submenu[ $root_slug ][] = array(
			/* translators: Name of the screen where content is sent for translation and translations are managed: in the WPML menu, as the title of that screen, and as the text of links that open it. Plural noun. */
			__( 'Translations', 'wpml' ),
			'translate',
			$root_slug,
			/* translators: Name of the screen where content is sent for translation and translations are managed: in the WPML menu, as the title of that screen, and as the text of links that open it. Plural noun. */
			__( 'Translations', 'wpml' ),
		);
	}


	private function is_string_translation_manager_only() {
		if ( ! function_exists( 'wp_get_current_user' ) ) {
			return false;
		}
		$user = wp_get_current_user();
		$caps = $user && isset( $user->allcaps ) && is_array( $user->allcaps ) ? $user->allcaps : array();

		return ! empty( $caps[ self::ST_GRANT ] )
			&& empty( $caps['manage_translations'] )
			&& empty( $caps['wpml_manage_languages'] )
			&& empty( $caps['translate'] );
	}

	public function lower_root_cap_for_string_translation_managers() {
		if ( ! $this->is_string_translation_manager_only() ) {
			return;
		}

		global $menu, $submenu;
		if ( ! is_array( $menu ) ) {
			return;
		}

		$index = $this->find_wpml_root_index( $menu );
		if ( null === $index ) {
			return;
		}
		$old_root_slug = (string) $menu[ $index ][2];

		$menu[ $index ][1] = self::ST_GRANT;
		$menu[ $index ][2] = self::STRINGS_TAB_URL;

		if ( ! is_array( $submenu ) ) {
			$submenu = array();
		}
		$kept = array( $this->strings_submenu_entry() );
		foreach ( isset( $submenu[ $old_root_slug ] ) ? (array) $submenu[ $old_root_slug ] : array() as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry[1], $entry[2] ) ) {
				continue;
			}
			if ( self::STRINGS_TAB_URL === $entry[2] || $old_root_slug === $entry[2] ) {
				continue;
			}
			if ( $this->current_user_holds( (string) $entry[1] ) ) {
				$kept[] = $entry;
			}
		}
		unset( $submenu[ $old_root_slug ] );
		$submenu[ self::STRINGS_TAB_URL ] = $kept;
	}

	private function current_user_holds( $capability ) {
		$user = wp_get_current_user();
		$caps = $user && isset( $user->allcaps ) && is_array( $user->allcaps ) ? $user->allcaps : array();

		return ! empty( $caps[ $capability ] );
	}

	private function strings_submenu_entry() {
		return array(
			/* translators: Name of the Strings tab of WPML → Translations: the texts of the theme, the plugins and the site. Also used as link text and as a back-link to that tab. */
			__( 'Strings', 'wpml' ),
			self::ST_GRANT,
			self::STRINGS_TAB_URL,
			/* translators: Name of the Strings tab of WPML → Translations: the texts of the theme, the plugins and the site. Also used as link text and as a back-link to that tab. */
			__( 'Strings', 'wpml' ),
		);
	}

	public function readd_strings_submenu_for_string_translation_managers() {
		if ( ! $this->is_string_translation_manager_only() ) {
			return;
		}

		global $menu, $submenu;
		if ( ! is_array( $menu ) ) {
			return;
		}

		$index = $this->find_wpml_root_index( $menu );
		if ( null === $index ) {
			return;
		}
		$root_slug = (string) $menu[ $index ][2];

		foreach ( isset( $submenu[ $root_slug ] ) ? (array) $submenu[ $root_slug ] : array() as $entry ) {
			if ( is_array( $entry ) && isset( $entry[2] ) && self::STRINGS_TAB_URL === $entry[2] ) {
				return;
			}
		}

		$submenu[ $root_slug ][] = $this->strings_submenu_entry();
	}

	public function highlight_strings_entry_for_string_translation_managers( $file ) {
		if ( ! $this->is_string_translation_manager_only() ) {
			return $file;
		}
		if ( self::TRANSLATIONS_PAGE_SLUG !== \WPML\SuperGlobals\Request::page()
			|| 'strings' !== \WPML\SuperGlobals\Request::param( 'tab' ) ) {
			return $file;
		}

		return self::STRINGS_TAB_URL;
	}

	private function find_wpml_root_index( array $menu ) {
		foreach ( $menu as $index => $item ) {
			if ( is_array( $item ) && isset( $item[2] ) && 'WPML' === ( $item[0] ?? '' ) && 'WPML' === ( $item[3] ?? '' ) ) {
				return $index;
			}
		}

		return null;
	}

	public function reroute_hidden_items( $menu_item, $root_slug ) {
		if ( ! $menu_item instanceof WPML_Admin_Menu_Item ) {
			return $menu_item;
		}

		$slug = $menu_item->get_menu_slug();

		if ( in_array( $slug, $this->hidden_slugs, true ) ) {
			$menu_item->set_parent_slug( self::HIDDEN_PARENT_SLUG );
			return $menu_item;
		}

		if (
			in_array( $slug, $this->reused_slugs, true )
			&& ! $this->is_modern_declarative_item( $menu_item )
		) {
			$menu_item->set_parent_slug( self::HIDDEN_PARENT_SLUG );
		}

		return $menu_item;
	}

	private function is_modern_declarative_item( WPML_Admin_Menu_Item $menu_item ) {
		$fn = $menu_item->get_function();

		return is_array( $fn )
			&& isset( $fn[0] )
			&& is_object( $fn[0] )
			&& $fn[0] instanceof \WPML\UserInterface\Web\Core\SharedKernel\Config\Page;
	}

	public function remove_root_duplicate_submenu() {
		global $menu, $submenu;

		if ( empty( $submenu ) || ! is_array( $submenu ) ) {
			return;
		}

		$root_slug = $this->find_wpml_root_slug( $submenu );
		if ( ! $root_slug ) {
			return;
		}

		$top_level_title = $this->find_top_level_menu_title( (array) $menu, $root_slug );
		if ( null === $top_level_title ) {
			return;
		}

		if (
			isset( $submenu[ $root_slug ][0][2], $submenu[ $root_slug ][0][0] )
			&& $submenu[ $root_slug ][0][2] === $root_slug
			&& $submenu[ $root_slug ][0][0] === $top_level_title
		) {
			unset( $submenu[ $root_slug ][0] );
		}
	}

	private function find_top_level_menu_title( array $menu, $root_slug ) {
		foreach ( $menu as $entry ) {
			if (
				is_array( $entry )
				&& isset( $entry[2], $entry[0] )
				&& $entry[2] === $root_slug
			) {
				return (string) $entry[0];
			}
		}

		return null;
	}

	private function find_wpml_root_slug( array $submenu ) {
		$markers = array(
			'tm/menu/main.php',
			'tm/menu/settings',
			'wpml-ai-translation-billing',
			WPML_PLUGIN_FOLDER . '/menu/support.php',
			'wpml-activate-update',
		);

		foreach ( $submenu as $parent => $items ) {
			if ( ! is_array( $items ) ) {
				continue;
			}
			foreach ( $items as $item ) {
				if ( isset( $item[2] ) && in_array( $item[2], $markers, true ) ) {
					return (string) $parent;
				}
			}
		}

		return null;
	}

	public function register_add_ons_group_label( $menu_id ) {
		if ( 'WPML' !== $menu_id ) {
			return;
		}

		$has_add_on = defined( 'WPML_STICKY_LINKS_PATH' )
			|| defined( 'WPML_CMS_NAV_PLUGIN_PATH' )
			|| defined( 'WPML_IMPORT_ADMIN_PAGE_SLUG' );

		if ( ! $has_add_on ) {
			return;
		}

		do_action(
			'wpml_admin_menu_register_item',
			array(
				'order'      => self::ADD_ONS_LABEL_ORDER,
				/* translators: Title of the screen listing the WPML add-ons, and the item in the WPML menu that opens it. */
				'page_title' => __( 'Add-ons', 'sitepress' ),
				/* translators: Title of the screen listing the WPML add-ons, and the item in the WPML menu that opens it. */
				'menu_title' => '<span class="wpml-add-ons-group-label">' . esc_html__( 'Add-ons', 'sitepress' ) . '</span>',
				'capability' => 'wpml_manage_languages',
				'menu_slug'  => self::ADD_ONS_LABEL_SLUG,
				'function'   => array( $this, 'render_add_ons_label_placeholder' ),
			)
		);
	}

	public function render_add_ons_label_placeholder() {
	}

	public function print_add_ons_group_style() {
		$anchor = '#adminmenu .wp-submenu a[href$="page=' . esc_attr( self::ADD_ONS_LABEL_SLUG ) . '"]';
		$row    = '#adminmenu .wp-submenu li:has(a[href$="page=' . esc_attr( self::ADD_ONS_LABEL_SLUG ) . '"])';
		echo '<style id="wpml-add-ons-group-style">'
			. $row . '{background:#2c3339;}'
			. $anchor . '{'
			. 'pointer-events:none;cursor:default;'
			. 'text-transform:uppercase;font-size:10px;font-weight:600;'
			. 'letter-spacing:.04em;color:#fff;margin-top:6px;'
			. '}'
			. '</style>';
	}
}
