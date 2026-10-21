<?php

class WPML_Main_Admin_Menu {
	const MENU_ORDER_LANGUAGES                       = 100;
	const MENU_ORDER_THEMES_AND_PLUGINS_LOCALIZATION = 200;
	const MENU_ORDER_TAXONOMY_TRANSLATION            = 900;
	const MENU_ORDER_SETTINGS                        = 9900;
	const MENU_ORDER_MAX                             = 10000;

	const HIDDEN_PAGE_PRIORITY = 11;

	private $languages_menu_slug;
	private $root;
	private $sitepress;

	public function __construct( SitePress $sitepress ) {
		$this->sitepress           = $sitepress;
		$this->languages_menu_slug = WPML_PLUGIN_FOLDER . '/menu/languages.php';
	}

	public function configure() {
		$this->root = new WPML_Admin_Menu_Root(
			array(
				'menu_id'    => 'WPML',
				/* translators: The name of the plugin, used as the title of its screens and of its menu. It is a product name and stays as it is. */
				'page_title' => __( 'WPML', 'sitepress' ),
				/* translators: The name of the plugin, used as the title of its screens and of its menu. It is a product name and stays as it is. */
				'menu_title' => __( 'WPML', 'sitepress' ),
				'capability' => 'wpml_manage_languages',
				'menu_slug',
				'function'   => null,
				'icon_url'   => null,
			)
		);

		$this->root->init_hooks();

		if ( $this->sitepress->is_setup_complete() ) {
			$this->languages();
			do_action( 'icl_wpml_top_menu_added' );

			$this->themes_and_plugins_localization();

			if ( ! $this->is_tm_active() ) {
				$this->translation_options();
			}

			$this->taxonomy_translation();

			do_action( 'wpml_core_admin_menus_added' );
		}

		if ( ! $this->sitepress->is_setup_complete() || ! $this->is_wpml_configured() ) {
			$this->wizard();
		} else {
			add_action( 'admin_menu', array( $this, 'register_hidden_wizard_page' ), self::HIDDEN_PAGE_PRIORITY );
		}

		$this->support();

		do_action( 'wpml_core_admin_menus_completed' );
	}

	private function languages() {
		$menu = new WPML_Admin_Menu_Item();
		$menu->set_order( self::MENU_ORDER_LANGUAGES );
		/* translators: Name of the Languages screen: in the WPML menu, as the title of that screen, and as a column heading listing the languages of a piece of content. Plural noun. */
		$menu->set_page_title( __( 'Languages', 'sitepress' ) );
		/* translators: Name of the Languages screen: in the WPML menu, as the title of that screen, and as a column heading listing the languages of a piece of content. Plural noun. */
		$menu->set_menu_title( __( 'Languages', 'sitepress' ) );
		$menu->set_capability( 'wpml_manage_languages' );
		$menu->set_menu_slug( $this->languages_menu_slug );
		$this->root->add_item( $menu );
	}

	private function wizard() {
		$menu = new WPML_Admin_Menu_Item();
		$menu->set_order( 1 );
		/* translators: Title of the screen that sets WPML up step by step. */
		$menu->set_page_title( __( 'WPML Setup', 'sitepress' ) );
		/* translators: Item in the WPML menu that opens the screen where WPML is set up step by step. Noun. */
		$menu->set_menu_title( __( 'Setup', 'sitepress' ) );
		$menu->set_capability( 'wpml_manage_languages' );
		$menu->set_menu_slug( WPML_PLUGIN_FOLDER . '/menu/setup.php' );
		$this->root->add_item( $menu );
	}

	public function register_hidden_wizard_page() {
		add_submenu_page(
			WPML_Hide_Legacy_Top_Level_Menu::HIDDEN_PARENT_SLUG,
			/* translators: Title of the screen that sets WPML up step by step. */
			__( 'WPML Setup', 'sitepress' ),
			/* translators: Item in the WPML menu that opens the screen where WPML is set up step by step. Noun. */
			__( 'Setup', 'sitepress' ),
			'wpml_manage_languages',
			WPML_PLUGIN_FOLDER . '/menu/setup.php'
		);
	}

	private function is_wpml_configured() {
		return 2 <= count( $this->sitepress->get_active_languages() );
	}

	private function themes_and_plugins_localization() {
		$menu = new WPML_Admin_Menu_Item();
		$menu->set_order( self::MENU_ORDER_THEMES_AND_PLUGINS_LOCALIZATION );
		$menu->set_page_title( __( 'Theme and plugins localization', 'sitepress' ) );
		$menu->set_menu_title( __( 'Theme and plugins localization', 'sitepress' ) );
		$menu->set_capability( 'wpml_manage_theme_and_plugin_localization' );
		$menu->set_menu_slug( WPML_PLUGIN_FOLDER . '/menu/theme-localization.php' );
		$this->root->add_item( $menu );
	}

	private function is_tm_active() {
		return $this->sitepress->get_wp_api()->defined( 'WPML_TM_VERSION' );
	}

	private function translation_options() {
		$menu = new WPML_Admin_Menu_Item();
		$menu->set_order( self::MENU_ORDER_SETTINGS );
		/* translators: Title of the settings screen, and the item in the WPML menu that opens it. */
		$menu->set_page_title( __( 'Settings', 'sitepress' ) );
		/* translators: Title of the settings screen, and the item in the WPML menu that opens it. */
		$menu->set_menu_title( __( 'Settings', 'sitepress' ) );
		$menu->set_capability( 'wpml_manage_translation_options' );
		$menu->set_menu_slug( WPML_PLUGIN_FOLDER . '/menu/translation-options.php' );
		$this->root->add_item( $menu );
	}

	private function taxonomy_translation() {
		$menu = new WPML_Admin_Menu_Item();
		$menu->set_order( self::MENU_ORDER_TAXONOMY_TRANSLATION );
		/* translators: Title of the screen where the names of categories, tags and other groupings are translated, and the item in the WPML menu that opens it. */
		$menu->set_page_title( __( 'Taxonomy translation', 'sitepress' ) );
		/* translators: Title of the screen where the names of categories, tags and other groupings are translated, and the item in the WPML menu that opens it. */
		$menu->set_menu_title( __( 'Taxonomy translation', 'sitepress' ) );
		$menu->set_capability( 'wpml_manage_taxonomy_translation' );
		$menu->set_menu_slug( WPML_PLUGIN_FOLDER . '/menu/taxonomy-translation.php' );
		$menu->set_function( array( $this->sitepress, 'taxonomy_translation_page' ) );
		$this->root->add_item( $menu );
	}

	private function support() {
		$menu_slug = WPML_PLUGIN_FOLDER . '/menu/support.php';

		$menu = new WPML_Admin_Menu_Item();
		$menu->set_order( self::MENU_ORDER_MAX );
		/* translators: Title of the screen that gathers facts for the support team, and the item in the WPML menu that opens it. Noun: help from the WPML support team. */
		$menu->set_page_title( __( 'Support', 'sitepress' ) );
		/* translators: Title of the screen that gathers facts for the support team, and the item in the WPML menu that opens it. Noun: help from the WPML support team. */
		$menu->set_menu_title( __( 'Support', 'sitepress' ) );
		$menu->set_capability( 'wpml_manage_support' );
		$menu->set_menu_slug( $menu_slug );
		$this->root->add_item( $menu );

		if ( $this->sitepress->is_setup_complete() ) {
			$this->debug_information_menu( $menu_slug );
		}
	}

	private function debug_information_menu( $parent_slug ) {
		$menu = new WPML_Admin_Menu_Item();
		$menu->set_parent_slug( $parent_slug );
		/* translators: Title of the screen that lists facts about the site for finding problems, and the item in the WPML menu that opens it. */
		$menu->set_page_title( __( 'Debug information', 'sitepress' ) );
		/* translators: Title of the screen that lists facts about the site for finding problems, and the item in the WPML menu that opens it. */
		$menu->set_menu_title( __( 'Debug information', 'sitepress' ) );
		$menu->set_capability( 'wpml_manage_support' );
		$menu->set_menu_slug( WPML_PLUGIN_FOLDER . '/menu/debug-information.php' );
		$this->root->add_item( $menu );
	}
}
