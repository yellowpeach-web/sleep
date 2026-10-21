<?php

require_once __DIR__ . '/inc/constants-since-5-0.php';

use WPML\classes\wizard\WPML_Reset_Wizard_Ajax_Hooks;
use WPML\AdminLanguageSwitcher\GlobalScreens;
use WPML\FP\Fns;
use WPML\FP\Logic;
use WPML\FP\Obj;
use WPML\FP\Str;
use WPML\LIB\WP\Url;
use function WPML\FP\pipe;
use WPML\Hooks\WpmlSavePostHooks;
use WPML\Core\Component\PostHog\Application\Service\Event\EventInstanceService;
use WPML\Core\Component\CustomFieldPreferences\Domain\ElementType;
use WPML\Language\CacheKeyClassifier;
use WPML\LanguageSwitcher\PreservedArguments;
use WPML\TM\Settings\PreferenceResolver;
use WPML\TM\Settings\PreferenceWriter;
use WPML\TM\Settings\RequestSettings;

class SitePress extends WPML_WPDB_User implements
	IWPML_Current_Language,
	IWPML_Taxonomy_State,
	\WPML\Core\ISitePress {
	const AFTER_ST_PLUGIN_LOADED_HOOK     = - PHP_INT_MAX + 1;
	const INIT_HOOK_TRANSLATIONS_PRIORITY = -1;

	private $taxonomy_translation;
	private $template_real_path;
	private $post_translation;
	private $term_translation;
	private $post_duplication;
	private $term_actions;
	private $scripts_handler;
	private $language_setter;
	private $post_translation_metadata_initializer;
	private $term_query_filter;
	private $settings;
	private $active_languages = array();

	private $active_languages_retried = array();
	private $in_determine_locale = false;
	private $_admin_notices   = array();
	private $this_lang;
	private $wp_query;
	private $admin_language;
	private $user_preferences = array();
	private $wp_api;
	private $loaded_blog_id;

	private $original_language;

	private $language_switch_stack = array();

	public $locale_utils;

	public $footer_preview = false;

	public $icl_translations_cache;
	private $flags;
	public $icl_language_name_cache;
	public $icl_term_taxonomy_cache;

	private $wpml_term_adjust_id = null;

	private $current_request_data = array();

	public $ROOT_URL_PAGE_ID;

	private $wpml_save_post_hooks;

	function __construct() {
		do_action( 'wpml_before_startup' );
		global $pagenow, $sitepress_settings, $wpdb, $wpml_post_translations, $locale, $wpml_term_translations;

		parent::__construct( $wpdb );
		$this->locale_utils     = new WPML_Locale( $wpdb, $this, $locale );
		$sitepress_settings     = RequestSettings::load();
		$this->settings         = &$sitepress_settings;
		$this->post_translation = &$wpml_post_translations;
		$this->term_translation = &$wpml_term_translations;
		if ( $this->should_enable_capabilities() ) {
			wpml_enable_capabilities();
		}

		if ( null === $pagenow && is_multisite() ) {
			include WPML_PLUGIN_PATH . '/inc/hacks/vars-php-multisite.php';
		}

		if ( $this->settings ) {
			$this->verify_settings();
		}

		if ( isset( $_GET['page'], $_GET['debug_action'] ) && WPML_PLUGIN_FOLDER . '/menu/troubleshooting.php' === $_GET['page'] ) {
			ob_start();
		}

		require_once WPML_PLUGIN_PATH . '/classes/request-handling/class-wpml-ajax-commands.php';
		WPML_Ajax_Commands::add_hooks( $this );
		require_once WPML_PLUGIN_PATH . '/classes/request-handling/class-wpml-browser-language-responder.php';
		WPML_Browser_Language_Responder::add_hooks();

		$this->initialize_cache();

		$flags_factory = new WPML_Flags_Factory( $wpdb );
		$this->flags   = $flags_factory->create();

		add_action( 'init', array( $this, 'plugin_localization' ), self::INIT_HOOK_TRANSLATIONS_PRIORITY );
		add_action( 'plugins_loaded', array( $this, 'init' ), 1 );
		add_action( 'wp_loaded', array( $this, 'maybe_set_this_lang' ) );
		add_action( 'shutdown', array( $this, 'restore_language_state_at_request_end' ), PHP_INT_MAX );
		add_action( 'switch_blog', array( $this, 'init_settings' ), 10, 1 );
		add_action( 'wpml_translation_editor_stored', array( $this, 'sync_translation_editor_setting' ) );

		if ( is_admin() ) {
			$ajaxHook = new WPML_Reset_Wizard_Ajax_Hooks();
			$ajaxHook->add_hooks();
		}

		add_action( 'wpml_term_translation_created', array( 'WPML_Terms_Translations', 'link_translated_posts_action' ), 10, 3 );

		if ( $this->get_setting( 'existing_content_language_verified' ) && ( $this->get_setting( 'setup_complete' ) || ( ! empty( $_GET['page'] ) && $this->get_setting( 'setup_wizard_step' ) > 1 && $_GET['page'] == WPML_PLUGIN_FOLDER . '/menu/languages.php' ) ) ) {

			add_filter( 'comment_feed_join', array( $this, 'comment_feed_join' ) );

			add_filter( 'comments_clauses', array( $this, 'comments_clauses' ), 10, 2 );

			add_filter( 'pre_get_posts', array( $this, 'pre_get_posts' ) );

			if ( $pagenow === 'edit.php' ) {
				add_action( 'quick_edit_custom_box', array( 'WPML_Terms_Translations', 'quick_edit_terms_removal' ), 10, 2 );
			}

			add_filter( 'get_pages', array( $this, 'exclude_other_language_pages2' ), 10, 2 );
			add_filter( 'wp_dropdown_pages', array( $this, 'wp_dropdown_pages' ) );

			add_filter( 'get_comment_link', array( $this, 'get_comment_link_filter' ) );

			$this->set_term_filters_and_hooks();

			add_action( 'parse_query', array( $this, 'parse_query' ) );

			\WPML\Request\Adapter\Ajax::register(
				'wpml_save_term',
				\WPML\Request\Policy\Policy::authorize(
					function () {
						$taxonomy = isset( $_POST['taxonomy'] ) && is_string( $_POST['taxonomy'] ) ? get_taxonomy( sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) ) : null;

						return $taxonomy && current_user_can( $taxonomy->cap->edit_terms );
					},
					\WPML\Request\Policy\Authenticity::wpmlActionNonce( 'wpml_save_term' ),
					'may edit terms of the posted taxonomy (edit_terms capability of that taxonomy)'
				),
				array( 'WPML_Post_Edit_Ajax', 'wpml_save_term_action' )
			);
			\WPML\Request\Adapter\Ajax::register( 'wpml_switch_post_language', \WPML\Request\Policy\Policy::capability( 'edit_posts', \WPML\Request\Policy\Authenticity::actionNonce( 'wpml_switch_post_language', 'nonce' ) ), array( 'WPML_Post_Edit_Ajax', 'wpml_switch_post_language' ) );
			\WPML\Request\Adapter\Ajax::register( 'wpml_get_default_lang', \WPML\Request\Policy\Policy::capability( 'edit_posts', \WPML\Request\Policy\Authenticity::actionNonce( 'wpml_get_default_lang', '_icl_nonce' ) ), array( 'WPML_Post_Edit_Ajax', 'wpml_get_default_lang' ) );

			\WPML\Request\Adapter\Ajax::register( 'wpml_get_terms_and_labels_for_taxonomy_table', \WPML\Request\Policy\Policy::capability( [ 'wpml_manage_taxonomy_translation', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( 'wpml_taxonomy_translation_nonce', 'nonce' ) ), array( 'WPML_Taxonomy_Translation_Table_Display', 'wpml_get_terms_and_labels_for_taxonomy_table' ) );

			\WPML\Request\Adapter\Ajax::register( 'wpml_generate_term_slug', \WPML\Request\Policy\Policy::capability( 'edit_posts', \WPML\Request\Policy\Authenticity::actionNonce( 'wpml_generate_unique_slug_nonce', 'nonce' ) ), array( $this->get_term_actions_helper(), 'generate_unique_term_slug_ajax_handler' ) );

			add_filter( 'pre_option_default_category', array( $this, 'pre_option_default_category' ) );
			add_filter( 'update_option_default_category', array( $this, 'update_option_default_category' ), 1, 2 );
			add_filter( 'pre_update_option_default_category', array( $this, 'pre_update_option_default_category' ), 10, 2 );

			add_action( 'admin_enqueue_scripts', array( $this, 'backend_js' ) );

			add_action( 'wp_head', array( $this, 'rtl_fix' ) );
			add_action( 'admin_print_styles', array( $this, 'rtl_fix' ) );
			add_action( 'admin_init', array( $this, 'rtl_fix' ) );

			add_action( 'restrict_manage_posts', array( $this, 'restrict_manage_posts' ) );

			add_filter( 'feed_link', array( $this, 'feed_link' ) );

			add_filter( 'post_comments_feed_link', array( $this, 'post_comments_feed_link' ) );
			add_filter( 'trackback_url', array( $this, 'trackback_url' ) );
			add_filter( 'user_trailingslashit', array( $this, 'user_trailingslashit' ), 1, 2 );

			( new \WPML\UrlHandling\HomeOptionInTemplates( $this ) )->add_hooks();

			add_filter( 'post_type_archive_link', array( $this, 'post_type_archive_link_filter' ), 10, 2 );

			add_filter( 'author_link', array( $this, 'author_link' ) );

			add_action( 'query_vars', array( $this, 'query_vars' ) );
			add_filter( 'language_attributes', array( $this, 'language_attributes' ) );
			add_filter( 'locale', array( $this, 'locale_filter' ), 10, 1 );
			add_filter( 'pre_determine_locale', array( $this, 'pre_determine_locale_filter' ), 10, 1 );
			add_filter( 'pre_option_page_on_front', array( $this, 'pre_option_page_on_front' ) );
			add_filter( 'pre_option_page_for_posts', array( $this, 'pre_option_page_for_posts' ) );
			add_filter( 'pre_option_wp_page_for_privacy_policy', [ $this, 'pre_option_wp_page_for_privacy_policy' ] );

			$sticky_posts_loader = new WPML_Sticky_Posts_Loader( $this );
			$sticky_posts_loader->add_hooks();

			add_filter( 'trashed_post', array( $this, 'fix_trashed_front_or_posts_page_settings' ) );
			add_filter( 'delete_post', array( $this, 'fix_trashed_front_or_posts_page_settings' ) );

			$this->add_page_options_core_reset_hooks();

			add_action( 'wp', array( $this, 'set_wp_query' ) );
			add_action( 'personal_options_update', array( $this, 'save_user_options' ) );
			add_action( 'edit_user_profile_update', array( $this, 'save_user_options' ) );

			if ( ! is_admin() ) {
				add_action( 'wp_head', array( $this, 'meta_generator_tag' ) );
			}

			if ( $this->is_setup_complete() ) {
				$icl_nav_menu = new WPML_Nav_Menu( $this, $wpdb, $wpml_post_translations, $wpml_term_translations );
				$icl_nav_menu->init_hooks();
			}

			add_action( 'wp_login', array( $this, 'reset_admin_language_cookie' ) );

			$this->handle_head_hreflang();

			add_filter( 'icl_get_extra_debug_info', array( $this, 'add_extra_debug_info' ) );

			$this->wpml_save_post_hooks = new \WPML\Hooks\WpmlSavePostHooks( $this, $wpdb );
			$this->wpml_save_post_hooks->init_hooks();
		} else {
			add_action(
				'admin_enqueue_scripts',
				function () {
					$this->backend_js( false );
				}
			);
		}

		add_filter( 'core_version_check_locale', array( $this, 'wp_upgrade_locale' ) );

		if ( $pagenow === 'post.php' && isset( $_REQUEST['action'], $_GET['post'] ) && $_REQUEST['action'] === 'edit' ) {
			add_action( 'init', '_icl_trash_restore_prompt' );
		}

		add_action( 'init', array( $this, 'register_assets' ), 2 );

		add_action( 'admin_enqueue_scripts', array( $this, 'js_load' ), 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'js_load' ), 2 );
		add_filter( 'url_to_postid', array( $this, 'url_to_postid' ) );
		$xml_config_log_factory = new WPML_XML_Config_Log_Factory();
		$log                    = $xml_config_log_factory->create_log();

		if ( $this->is_setup_complete() ) {
			$xml_config_log_notice = $xml_config_log_factory->create_notice();
			$xml_config_log_notice->add_hooks();
		}

		$wpml_config_update_integrator = new WPML_Config_Update_Integrator( $log );
		$wpml_config_update_integrator->add_hooks();

		add_action( 'core_upgrade_preamble', array( $this, 'update_index_screen' ) );
		add_filter( 'get_search_form', array( $this, 'get_search_form_filter' ) );
		$this->api_hooks();
		add_action( 'wpml_loaded', array( $this, 'load_dependencies' ), 10000 );
		do_action( 'wpml_after_startup' );

        new WPML_Term_Display_As_Translated_Adjust_Count(
			$this,
			$this->wpdb
		);

	}

	public function api_hooks() {
		add_filter( 'WPML_get_setting', array( $this, 'filter_get_setting' ), 10, 2 );
		add_filter( 'WPML_get_current_language', array( $this, 'get_current_language' ), 10, 0 );
		add_filter( 'WPML_get_user_admin_language', array( $this, 'get_user_admin_language_filter' ), 10, 2 );
		add_filter( 'WPML_is_admin_action_from_referer', array( $this, 'check_if_admin_action_from_referer' ), 10, 0 );
		add_filter( 'WPML_current_user', array( $this, 'get_current_user' ), 10, 0 );

		add_filter( 'wpml_get_setting', array( $this, 'filter_get_setting' ), 10, 2 );
		add_action( 'wpml_set_setting', array( $this, 'action_set_setting' ), 10, 3 );
		add_filter( 'wpml_get_language_cookie', array( $this, 'get_language_cookie' ), 10, 0 );
		add_filter( 'wpml_current_language', array( $this, 'get_current_language' ), 10, 0 );
		add_filter( 'wpml_get_user_admin_language', array( $this, 'get_user_admin_language_filter' ), 10, 2 );
		add_filter( 'wpml_is_admin_action_from_referer', array( $this, 'check_if_admin_action_from_referer' ), 10, 0 );
		add_filter( 'wpml_current_user', array( $this, 'get_current_user' ), 10, 0 );

		add_filter( 'wpml_new_post_source_id', array( $this, 'get_new_post_source_id' ), 10, 1 );

		add_filter( 'wpml_translatable_documents', array( $this, 'get_translatable_documents_filter' ), 10, 1 );
		add_filter( 'wpml_is_translated_post_type', array( $this, 'is_translated_post_type_filter' ), 10, 2 );
		add_filter( 'wpml_is_display_as_translated_post_type', array( $this, 'is_display_as_translated_post_type_filter' ), 10, 2 );

		add_filter( 'wpml_is_translated_taxonomy', array( $this, 'is_translated_taxonomy_filter' ), 10, 2 );

		add_filter( 'wpml_get_element_translations_filter', array( $this, 'get_element_translations_filter' ), 10, 6 );
		add_filter( 'wpml_get_element_translations', array( $this, 'get_element_translations_filter' ), 10, 6 );
		add_filter( 'wpml_is_original_content', array( $this, 'is_original_content_filter' ), 10, 3 );
		add_filter( 'wpml_original_element_id', array( $this, 'get_original_element_id_filter' ), 10, 3 );
		add_filter( 'wpml_element_trid', array( $this, 'get_element_trid_filter' ), 10, 3 );

		add_filter( 'wpml_is_rtl', array( $this, 'is_rtl' ) );

		add_filter( 'wpml_home_url', 'wpml_get_home_url_filter', 10 );
		add_filter( 'wpml_active_languages', 'wpml_get_active_languages_filter', 10, 2 );
		add_filter( 'wpml_display_language_names', 'wpml_display_language_names_filter', 10, 5 );
		add_filter( 'wpml_display_single_language_name', array( $this, 'get_display_single_language_name_filter' ), 10, 2 );
		add_filter( 'wpml_element_link', 'wpml_link_to_element_filter', 10, 7 );
		add_filter( 'wpml_object_id', 'wpml_object_id_filter', 10, 4 );
		add_filter( 'wpml_translated_language_name', 'wpml_translated_language_name_filter', 10, 3 );
		add_filter( 'wpml_default_language', 'wpml_get_default_language_filter', 10, 1 );
		add_filter( 'wpml_post_language_details', 'wpml_get_language_information', 10, 2 );

		add_action( 'wpml_add_language_form_field', 'wpml_add_language_form_field_action' );
		add_shortcode( 'wpml_language_form_field', 'wpml_language_form_field_shortcode' );

		add_filter( 'wpml_element_translation_type', 'wpml_get_element_translation_type_filter', 10, 3 );
		add_filter( 'wpml_element_has_translations', 'wpml_element_has_translations_filter', 10, 3 );
		add_filter( 'wpml_content_translations', 'wpml_get_content_translations_filter', 10, 3 );
		add_filter( 'wpml_master_post_from_duplicate', 'wpml_get_master_post_from_duplicate_filter' );
		add_filter( 'wpml_post_duplicates', 'wpml_get_post_duplicates_filter' );
		add_filter( 'wpml_element_type', 'wpml_element_type_filter' );

		add_filter( 'wpml_setting', 'wpml_get_setting_filter', 10, 3 );
		add_filter( 'wpml_sub_setting', 'wpml_get_sub_setting_filter', 10, 4 );
		add_filter( 'wpml_language_is_active', 'wpml_language_is_active_filter', 10, 2 );
		add_filter( 'wpml_requested_language', 'wpml_requested_language_filter', 10, 4 );

		add_action( 'wpml_admin_make_post_duplicates', 'wpml_admin_make_post_duplicates_action', 10, 1 );

		add_action( 'wpml_make_post_duplicates', 'wpml_make_post_duplicates_action', 10, 1 );

		add_filter( 'wpml_element_language_details', 'wpml_element_language_details_filter', 10, 2 );
		add_action( 'wpml_set_element_language_details', array( $this, 'set_element_language_details_action' ), 10, 1 );
		add_filter( 'wpml_element_language_code', 'wpml_element_language_code_filter', 10, 2 );
		add_filter( 'wpml_elements_without_translations', 'wpml_elements_without_translations_filter', 10, 2 );

		add_action( 'wpml_switch_language', 'wpml_switch_language_action', 10, 1 );
	}

	function init() {

		\WPML\TM\Jobs\JobLog::init();

		do_action( 'wpml_before_init' );
		$this->locale_utils->init();
		$this->maybe_set_this_lang();

		if ( function_exists( 'w3tc_add_action' ) ) {
			w3tc_add_action( 'w3tc_object_cache_key', 'w3tc_translate_cache_key_filter' );
		}

		$this->get_user_preferences();
		$this->set_admin_language();

		if ( $this->get_setting( 'existing_content_language_verified' ) ) {
			add_action(
				'wpml_verify_post_translations',
				array(
					$this,
					'verify_post_translations_action',
				),
				10,
				1
			);

			if ( 2 === (int) $this->get_setting( 'language_negotiation_type' ) ) {
				add_filter( 'allowed_redirect_hosts', array( $this, 'allowed_redirect_hosts' ) );
			}

			$this->move_current_language_to_the_top();

			add_filter( 'mod_rewrite_rules', array( $this, 'rewrite_rules_filter' ), 10, 1 );

			if (
				is_admin() && $this->get_setting( 'setup_complete' )
				&& ! $this->get_wp_api()
							->is_translation_queue_page() && ! $this->get_wp_api()
																	->is_string_translation_page()
			) {
				if ( apply_filters( 'wpml_show_admin_language_switcher', ! GlobalScreens::isCurrent() ) ) {
					add_action( 'wp_before_admin_bar_render', array( $this, 'admin_language_switcher' ) );
				} else {
					$this->set_this_lang( 'all' );
				}
			}
		}

		if ( $this->is_rtl() ) {
			$GLOBALS['text_direction'] = 'rtl';
		}

		if ( ! wpml_is_ajax()
			&& is_admin()
			&& ! $this->get_setting( 'dont_show_help_admin_notice' )
			&& ! $this->get_setting( 'setup_complete' )
		) {
			$page = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_URL );
			if (
				current_user_can( 'manage_options' )
				&& ! Str::includes( 'menu/setup.php', $page )
			) {
				add_action( 'admin_notices', array( $this, 'help_admin_notice' ) );
			}
		}

		if ( ! defined( 'ICL_LANGUAGE_CODE' ) ) {
			define( 'ICL_LANGUAGE_CODE', $this->this_lang );
		}

		$language_details = $this->get_language_details( ICL_LANGUAGE_CODE );

		if ( ! defined( 'ICL_LANGUAGE_NAME' ) ) {
			$display_name = isset( $language_details['display_name'] ) ? $language_details['display_name'] : null;
			define( 'ICL_LANGUAGE_NAME', $display_name );
		}

		if ( ! defined( 'ICL_LANGUAGE_NAME_EN' ) ) {
			$english_name = isset( $language_details['english_name'] ) ? $language_details['english_name'] : null;
			define( 'ICL_LANGUAGE_NAME_EN', $english_name );
		}

		if ( defined( 'WPML_LOAD_API_SUPPORT' ) ) {
			require_once WPML_PLUGIN_PATH . '/inc/wpml-api.php';
		}

		add_action( 'wp_footer', array( $this, 'display_wpml_footer' ), 20 );

		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			add_action( 'xmlrpc_call', array( $this, 'xmlrpc_call_actions' ) );
			add_filter( 'xmlrpc_methods', array( $this, 'xmlrpc_methods' ) );
		}

		if ( is_admin()
			&& ( $this->is_taxonomy_related_page()
					|| $this->is_saving_taxonomy_labels() )
		) {
			$this->switch_to_admin_language();
			add_action( 'init', array( $this, 'remove_admin_language_switcher' ) );
		}

		if (
			$this->get_setting( 'just_reactivated' )
			&& ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) )
			&& ! has_action( 'init', [ $this, 'rebuild_language_information' ] )
		) {
			add_action( 'init', [ $this, 'rebuild_language_information' ], 1000 );
		}

		if ( is_admin() ) {
			$mo_file_search = new WPML_MO_File_Search( $this );
			add_action( 'after_switch_theme', array( $mo_file_search, 'reload_theme_dirs' ) );
		}

		do_action( 'wpml_after_init' );
		do_action( 'wpml_loaded', $this );

		if ( isset( $_GET['page'] )
			&& WPML_PLUGIN_FOLDER . '/menu/taxonomy-translation.php' === $_GET['page']
			&& is_admin()
		) {
			$this->taxonomy_translation = new WPML_Taxonomy_Translation( '', array(), new WPML_UI_Screen_Options_Factory( $this ) );
		}
	}

	public function maybe_set_this_lang() {
		global $wpml_request_handler, $pagenow, $wpml_language_resolution, $mode;

		$isLoadingATEAutomaticTranslationWidget = strpos( $_SERVER['REQUEST_URI'], 'ate-widget' );
		if ( ! defined( 'WP_ADMIN' ) && isset( $_SERVER['HTTP_HOST'] ) && ! empty( $_SERVER['HTTP_HOST'] ) && $_SERVER['HTTP_HOST'] && ! defined( 'WP_CLI' ) && did_action( 'init' ) && ! $isLoadingATEAutomaticTranslationWidget ) {
			require_once WPML_PLUGIN_PATH . '/classes/request-handling/redirection/wpml-frontend-redirection.php';
			$redirect_helper = _wpml_get_redirect_helper();
			$redirection     = new WPML_Frontend_Redirection(
				$this,
				$wpml_request_handler,
				$redirect_helper,
				$wpml_language_resolution
			);
			$this->set_this_lang( $redirection->maybe_redirect() );
		} else {
			$this->set_this_lang( $wpml_request_handler->get_requested_lang() );
			$this->enforce_single_working_language_on_post_new();
		}

		if (
			! apply_filters( 'wpml_should_skip_saving_language_in_cookies', false )
			&& ! $wpml_request_handler->is_language_from_referer()
		) {
			$wpml_request_handler->set_language_cookie( $this->this_lang );
		}

		if ( $pagenow === 'upload.php' && isset( $mode ) && 'grid' === $mode ) {
			$_GET['lang']      = null;
			$_GET['admin_bar'] = null;
		}
	}

	private function enforce_single_working_language_on_post_new() {
		global $pagenow, $wpml_request_handler;

		if ( $pagenow !== 'post-new.php' ) {
			return;
		}

		$requested_lang = isset( $_GET['lang'] ) ? sanitize_text_field( wp_unslash( $_GET['lang'] ) ) : '';
		$working_lang   = '' !== $requested_lang ? $requested_lang : $wpml_request_handler->get_raw_cookie_lang();

		if ( 'all' !== $working_lang && 'all' !== $this->this_lang ) {
			return;
		}

		$target = $this->user_lang_by_authcookie();
		if ( ! $target || 'all' === $target || ! $this->is_active_language( $target ) ) {
			$target = $this->get_default_language();
		}

		$this->switch_lang( $target, true );
	}

	function load_dependencies() {
		do_action( 'wpml_load_dependencies' );
	}

	function set_term_filters_and_hooks() {
		add_action( 'delete_term', array( $this, 'delete_term' ), 1, 3 );
		add_action( 'set_object_terms', array( 'WPML_Terms_Translations', 'set_object_terms_action' ), 10, 6 );
		add_action( 'created_term', array( 'WPML_Terms_Translations', 'clear_term_slug_exist_cache' ) );
		add_action( 'edited_term', array( 'WPML_Terms_Translations', 'clear_term_slug_exist_cache' ) );

		if ( ( isset( $_GET['action'] ) && 'ajax-tag-search' === $_GET['action'] ) || ( isset( $_POST['action'] ) && 'get-tagcloud' === $_POST['action'] ) ) {
			add_filter( 'get_terms', array( 'WPML_Terms_Translations', 'get_terms_filter' ), 10, 2 );
		}

		add_filter( 'terms_clauses', array( $this, 'terms_clauses' ), 10, 3 );
		add_action( 'create_term', array( $this, 'create_term' ), 1, 3 );
		add_action( 'edit_term', array( $this, 'create_term' ), 1, 3 );
		add_filter( 'get_terms_args', array( $this, 'get_terms_args_filter' ), 10, 2 );

		add_action( 'parse_query', array( 'WPML_Term_Query_Opt_Out', 'note_query_in_flight' ), 0 );
		add_action( 'posts_selection', array( 'WPML_Term_Query_Opt_Out', 'forget_query_in_flight' ) );
		add_filter( 'get_edit_term_link', array( $this, 'get_edit_term_link' ), 1, 4 );
		add_action( 'deleted_term_relationships', array( $this, 'deleted_term_relationships' ), 10, 3 );
		if ( (bool) $this->get_setting( 'auto_adjust_ids' ) ) {
			add_action( 'wp_list_pages_excludes', array( $this, 'adjust_wp_list_pages_excludes' ) );
			if ( ( ! $this->get_wp_api()
						->is_admin()
				|| $this->get_wp_api()
						->constant( 'DOING_AJAX' ) )
				    && ! wpml_is_rest_request()
			) {
				add_filter( 'get_term', array( $this, 'get_term_adjust_id' ), 1, 1 );
				add_action( 'edited_term', array( $this, 'edited_term_action' ) );
				add_filter( 'get_pages', array( $this, 'get_pages_adjust_ids' ), 1, 2 );
			}
		}
		add_action( 'clean_term_cache', array( $this, 'clear_elements_cache' ), 10, 2 );
	}

	function remove_admin_language_switcher() {
		remove_action( 'wp_before_admin_bar_render', array( $this, 'admin_language_switcher' ) );
	}

	function rebuild_language_information() {
		global $iclTranslationManagement;

		if ( $iclTranslationManagement && $iclTranslationManagement->add_missing_language_information() ) {
			$this->set_setting( 'just_reactivated', 0 );
			$this->save_settings();
		}
	}

	function setup() {
		return $this->get_setting( 'setup_complete' );
	}

	public function user_lang_by_authcookie() {
		global $current_user;

		if ( ! isset( $current_user ) ) {
			$username = '';
			if ( function_exists( 'wp_parse_auth_cookie' ) ) {
				$cookie_data = wp_parse_auth_cookie();
				$username    = $cookie_data['username'] ?? '';
			}
			$user_obj = new WP_User( 0, $username );
		} else {
			$user_obj = $current_user;
		}
		$user_id   = $user_obj->ID ?? 0;
		$user_lang = $this->get_user_admin_language( $user_id );
		$user_lang = $user_lang ? $user_lang : $this->get_current_language();

		return $user_lang;
	}

	function get_current_user() {
		global $current_user;

		return $current_user !== null ? $current_user : new WP_User();
	}

	private function should_enable_capabilities() {
		return is_admin()
			&& ! \WPML\Setup\Initializer::settingsAreUnrecoverable()
			&& ! $this->get_setting( 'icl_capabilities_verified' );
	}

	function check_if_admin_action_from_referer() {
		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '';

		return strpos( strtolower( $referer ), strtolower( admin_url() ) ) === 0;
	}

	public function is_admin_originated_rest_request() {
		global $wpml_request_handler;

		if ( ! $wpml_request_handler instanceof \WPML\Language\Detection\Rest ) {
			return false;
		}

		return $this->check_if_admin_action_from_referer();
	}

	public function show_management_column_content( $post_type ) {
		$custom_columns = new WPML_Custom_Columns( $this );

		return $custom_columns->show_management_column_content( $post_type );
	}

	function initialize_cache() {
		require_once WPML_PLUGIN_PATH . '/inc/cache.php';
	}

	function get_translations_cache() {
		if ( ! isset( $this->icl_translations_cache ) ) {
			$this->icl_translations_cache = new icl_cache();
		}

		return $this->icl_translations_cache;
	}

	function get_language_name_cache() {
		if ( ! $this->icl_language_name_cache ) {
			$this->icl_language_name_cache = \WPML\Language\ActiveLanguagesReadModel::cache();
		}

		return $this->icl_language_name_cache;
	}

	public function clear_language_name_cache() {
		global $switched;

		$this->active_languages_retried = array();

		if ( ! empty( $switched ) ) {
			return false;
		}

		$cleared = true;
		if ( $this->icl_language_name_cache ) {
			return false !== $this->icl_language_name_cache->clear();
		} else {
			if ( false === wpml_language_cache_rotate_epoch() ) {
				return false;
			}

			$cache = get_option( '_icl_cache' );
			if ( is_array( $cache ) && array_key_exists( 'language_name_cache_class', $cache ) ) {
				unset( $cache['language_name_cache_class'] );
				update_option( '_icl_cache', $cache );
			}
			delete_option( WPML_LANGUAGE_DETAILS_CACHE_OPTION );
			$cleared = wpml_language_cache_delete_shards();
			icl_cache::record_global_clear( 'language_name_cache_class' );
		}

		return $cleared;
	}

	public function set_admin_language( $admin_language = false ) {
		$default_language     = $this->get_default_language();
		$this->admin_language = $admin_language ? $admin_language : $this->user_lang_by_authcookie();

		$lang_codes = $this->get_supported_language_codes();
		if ( (bool) $this->admin_language === true && ! in_array( $this->admin_language, $lang_codes, true ) ) {
			delete_user_meta( $this->get_current_user()->ID, 'icl_admin_language' );
		}
		if ( empty( $this->settings['admin_default_language'] ) || ! in_array(
			$this->settings['admin_default_language'],
			array_merge( $lang_codes, array( '_default_' ) ),
			true
		)
		) {
			$this->settings['admin_default_language'] = '_default_';
			$this->save_settings();
		}

		if ( ! $this->admin_language ) {
			$this->admin_language = $this->settings['admin_default_language'];
		}
		if ( $this->admin_language === '_default_' && $default_language ) {
			$this->admin_language = $default_language;
		}
	}

	function get_admin_language() {
		if ( $this->is_wpml_switch_language_triggered() ) {
			return $this->get_current_language();
		}

		return $this->user_lang_by_authcookie();
	}

	public function is_wpml_switch_language_triggered() {
		return isset( $GLOBALS['icl_language_switched'] ) ? true : false;
	}

	public function restore_wpml_switch_language_triggered( $is_switched ) {
		if ( $is_switched ) {
			$GLOBALS['icl_language_switched'] = true;
		} else {
			unset( $GLOBALS['icl_language_switched'] );
		}
	}

	function is_post_edit_screen() {
		global $pagenow;

		$action = isset( $_GET['action'] ) ? $_GET['action'] : '';

		return $pagenow === 'post-new.php' || ( $pagenow === 'post.php' && ( 0 === strcmp( $action, 'edit' ) ) );
	}

	function get_user_admin_language_filter( $value, $user_id ) {
		return $this->get_user_admin_language( $user_id );
	}

	function get_user_admin_language( $user_id, $reload = false ) {
		$user_admin_language = new WPML_User_Admin_Language( $this );

		return $user_admin_language->get( $user_id, $reload );
	}

	function taxonomy_translation_page() {
		if ( isset( $_GET['sync'] ) && $_GET['sync'] == '1' && isset( $_GET['taxonomy'] ) && ! isset( $_GET['event_captured'] ) ) {
			$taxonomy = sanitize_text_field( $_GET['taxonomy'] );

			$event_props = array(
				'taxonomy' => $taxonomy,
				'source'   => 'admin_notice',
			);

			\WPML\PostHog\Event\CaptureEvent::capture(
				( new EventInstanceService() )->getTaxonomyHierarchySyncLinkClickedEvent( $event_props )
			);
		}

		$this->taxonomy_translation->render();
	}

	function init_settings( $blog_id ) {
		$blog_id = (int) $blog_id;

		if ( ! isset( $this->loaded_blog_id ) || $this->loaded_blog_id != $blog_id ) {
			$this->loaded_blog_id = $blog_id;
			$this->settings       = RequestSettings::reload();
			\WPML\TaxonomyTermTranslation\DefaultCategoryDeleteProtection::resetProtectedTermIds();
			$default_lang_code    = isset( $this->settings['default_language'] ) ? $this->settings['default_language'] : false;
			load_wpml_url_converter( $this->settings, false, $default_lang_code );
		}
	}

	function save_settings( $settings = null ) {
		if ( null !== $settings ) {
			foreach ( $settings as $k => $v ) {
				if ( is_array( $v ) ) {
					foreach ( $v as $k2 => $v2 ) {
						$this->settings[ $k ][ $k2 ] = $v2;
					}
				} else {
					$this->settings[ $k ] = $v;
				}
			}
		}
		if ( ! empty( $this->settings ) ) {
			update_option( 'icl_sitepress_settings', $this->settings );
		}
		do_action( 'icl_save_settings', $settings );
	}

	function get_settings() {
		return $this->settings;
	}

	function filter_get_setting( $value, $key ) {
		return $this->get_setting( $key, $value );
	}

	function get_setting( $key, $default = false ) {
		return wpml_get_setting_filter( $default, $key );
	}

	function action_set_setting( $key, $value, $save_now ) {
		$this->set_setting( $key, $value, $save_now );
	}

	public function sync_translation_editor_setting( $stored_value ) {
		$translation_management = isset( $this->settings['translation-management'] ) && is_array( $this->settings['translation-management'] )
			? $this->settings['translation-management']
			: array();

		$translation_management['doc_translation_method'] = $stored_value;
		$this->settings['translation-management']         = $translation_management;
	}

	function set_setting( $key, $value, $save_now = false ) {
		return icl_set_setting( $key, $value, $save_now );
	}

	public function remove_setting( $key, $sub_key = null ) {
		return \WPML\TM\Settings\SettingsRemoval::remove( $key, $sub_key );
	}

	function get_user_preferences() {
		if ( ! isset( $this->user_preferences ) || ! $this->user_preferences ) {
			$this->user_preferences = get_user_meta( $this->get_current_user()->ID, '_icl_preferences', true );
		}
		if ( ( is_array( $this->user_preferences ) && $this->user_preferences == array( 0 => false ) ) || ! $this->user_preferences ) {
			$this->user_preferences = array();
		}
		if ( ! is_array( $this->user_preferences ) ) {
			$this->user_preferences = (array) $this->user_preferences;
		}

		return $this->user_preferences;
	}

	function set_user_preferences( $value ) {
		$this->user_preferences = $value;
	}

	function save_user_preferences() {
		update_user_meta( $this->get_current_user()->ID, '_icl_preferences', $this->user_preferences );
	}

	public function get_option( $option_name ) {
		return $this->get_setting( $option_name, null );
	}


	public function get_sitekey() {
		if ( ! class_exists( 'WP_Installer' ) ) {
			return $this->get_setting( 'site_key', null );
		}

		return WP_Installer::instance()->get_site_key( 'wpml' );
	}

	function verify_settings() {

		$verify_settings                          = new WPML_Verify_SitePress_Settings(
			$this->get_wp_api(),
			new WPML_Settings_Stored_Copy()
		);
		list( $this->settings, $update_settings ) = $verify_settings->verify( $this->settings );

		if ( $update_settings ) {
			$this->save_settings();
		}

		WPML_URL_Converter_Factory::follow_settings( $this->settings );
	}

	function get_active_languages( $refresh = false, $major_first = false, $order_by = 'english_name' ) {
		global $wpml_request_handler;

		$is_admin_ui = defined( 'WP_ADMIN' ) || $this->is_admin_originated_rest_request();
		$in_language = $is_admin_ui && $this->admin_language ? $this->admin_language : null;
		$in_language = $in_language === null ? $this->get_current_language() : $in_language;
		$in_language = $in_language ? $in_language : $this->get_default_language();

		$retry_key = $in_language . '|' . (int) (bool) $major_first . '|' . (string) $order_by;

		$active_languages = $this->get_languages( $in_language, true, $refresh, $major_first, $order_by );

		if ( ! isset( $active_languages[ $in_language ] ) && ! isset( $this->active_languages_retried[ $retry_key ] ) ) {
			$this->active_languages_retried[ $retry_key ] = true;

			$active_languages = $this->get_languages( $in_language, true, true, $major_first, $order_by );
		}

		$active_languages       = $active_languages ? $active_languages : array();
		$this->active_languages = $wpml_request_handler->show_hidden() ? $active_languages : array_diff_key( $active_languages, array_fill_keys( $this->get_setting( 'hidden_languages', array() ), 1 ) );

		return $this->active_languages;
	}

	function order_languages( $languages ) {

		$ordered_languages = array();
		if ( isset( $this->settings['languages_order'] ) && is_array( $this->settings['languages_order'] ) ) {
			foreach ( $this->settings['languages_order'] as $code ) {
				if ( isset( $languages[ $code ] ) ) {
					$ordered_languages[ $code ] = $languages[ $code ];
					unset( $languages[ $code ] );
				}
			}
		} else {
			$iclsettings['languages_order'] = array_keys( $languages );
			$this->save_settings( $iclsettings );
		}

		if ( ! empty( $languages ) ) {
			foreach ( $languages as $code => $lang ) {
				$ordered_languages[ $code ] = $lang;
			}
		}

		return $ordered_languages;
	}

	function is_active_language( $lang_code ) {
		$result           = false;
		$active_languages = $this->get_active_languages();
		foreach ( $active_languages as $lang ) {
			if ( $lang_code == $lang['code'] ) {
				$result = true;
				break;
			}
		}

		return $result;
	}

	public function get_languages( $lang = false, $active_only = false, $refresh = false, $major_first = false, $order_by = 'english_name' ) {
		if ( ! $lang ) {
			$lang = $this->get_default_language();
		}

		return \WPML\Language\ActiveLanguagesReadModel::rows( $lang, (bool) $active_only, (bool) $refresh, $major_first, $order_by );
	}

	public function get_supported_language_codes( $refresh = false ) {
		$cache    = icl_disable_cache() ? null : $this->get_language_name_cache();
		$code_set = $refresh || null === $cache
			? false
			: $cache->get( CacheKeyClassifier::SUPPORTED_LANGUAGE_CODES_KEY );

		if ( $this->is_valid_supported_language_code_set( $code_set ) ) {
			return array_map( 'strval', array_keys( $code_set ) );
		}

		if ( ! \WPML\Language\WpmlPresence::onCurrentBlog() ) {
			return array();
		}

		$codes = $this->normalize_supported_language_codes(
			$this->wpdb->get_col(
				"SELECT code FROM {$this->wpdb->prefix}icl_languages ORDER BY english_name ASC"
			)
		);

		if ( ! $codes ) {
			$languages = $this->get_languages();
			$codes     = is_array( $languages )
				? $this->normalize_supported_language_codes( array_keys( $languages ) )
				: array();
		}

		if ( $codes && null !== $cache ) {
			$cache->set(
				CacheKeyClassifier::SUPPORTED_LANGUAGE_CODES_KEY,
				array_fill_keys( $codes, true )
			);
			$cache->save_cache_if_required();
		}

		return $codes;
	}

	private function is_valid_supported_language_code_set( $code_set ) {
		if ( ! is_array( $code_set ) || ! $code_set ) {
			return false;
		}

		foreach ( $code_set as $code => $present ) {
			if ( ( ! is_string( $code ) && ! is_int( $code ) ) || '' === trim( (string) $code ) || true !== $present ) {
				return false;
			}
		}

		return true;
	}

	private function normalize_supported_language_codes( $codes ) {
		$normalized = array();

		foreach ( (array) $codes as $code ) {
			if ( ! is_scalar( $code ) ) {
				continue;
			}

			$code = trim( (string) $code );
			if ( '' !== $code ) {
				$normalized[ $code ] = true;
			}
		}

		return array_map( 'strval', array_keys( $normalized ) );
	}

	function get_language_details( $code ) {
		if ( ! $code ) {
			return false;
		}

		$displayLanguageCode = $this->get_wp_api()->is_front_end() ? $code : $this->admin_language;
		$details             = $this->get_language_name_cache()->get( 'language_details_' . $code . $displayLanguageCode );

		return $details ?: Obj::propOr( false, $code, $this->get_languages( $displayLanguageCode ) );
	}

	function get_language_code( $english_name ) {
		$wpdb = $this->wpdb;

		$code = $wpdb->get_var(
			$wpdb->prepare( " SELECT code FROM {$wpdb->prefix}icl_languages WHERE english_name = %s LIMIT 1", $english_name )
		);

		return $code;
	}

	function get_language_code_from_locale( $locale, $active_only = true ) {
		$active_clause = $active_only ? ' AND active = 1' : ' ORDER BY active DESC';

		$wpdb = $this->wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( " SELECT code, country, active, id FROM {$wpdb->prefix}icl_languages WHERE default_locale = %s{$active_clause}", $locale ) );

		if ( empty( $rows ) ) {
			return null;
		}

		$segments = array_map( 'strtoupper', array_filter( explode( '_', (string) $locale ) ) );
		$default  = strtolower( (string) $this->get_default_language() );

		$best      = null;
		$best_rank = null;
		foreach ( $rows as $row ) {
			$country = strtoupper( trim( (string) $row->country ) );
			$rank    = [
				$active_only ? 0 : ( 1 === (int) $row->active ? 0 : 1 ),
				'' !== $country && in_array( $country, $segments, true ) ? 0 : 1,
				'' !== $default && strtolower( (string) $row->code ) === $default ? 0 : 1,
				'' === $country ? 0 : 1,
				(int) $row->id,
			];
			if ( null === $best_rank || $rank < $best_rank ) {
				$best_rank = $rank;
				$best      = (string) $row->code;
			}
		}

		return $best;
	}

	function get_locale_from_language_code( $code ) {
		$wpdb = $this->wpdb;

		$locale = $wpdb->get_var(
			$wpdb->prepare( " SELECT default_locale FROM {$wpdb->prefix}icl_languages WHERE code = %s LIMIT 1", $code )
		);

		return $locale;
	}

	function get_default_language() {
		return $this->get_setting( 'default_language', false );
	}

	private function is_valid_language( $language ) {
		if ( null === $language ) {
			return false;
		}

		$active_languages  = $this->active_languages;
		$is_valid_language = $active_languages
			? isset( $active_languages[ $language ] )
			: in_array( $language, $this->get_supported_language_codes(), true );

		$is_wp_cli_request = defined( 'WP_CLI' ) && WP_CLI;
		$is_admin          = $this->get_wp_api()->is_admin();

		$is_valid_language = $is_valid_language
							 || \WPML\Languages\RemovedLanguages::browsableInAdminList( $language );

		return $is_valid_language || ( 'all' === $language && ( $is_admin || $is_wp_cli_request ) );
	}

	private function set_this_lang( $new_value ) {
		if ( $this->is_valid_language( $new_value ) ) {
			$this->this_lang = $new_value;
		}
	}

	public function get_this_lang() {
		return $this->this_lang;
	}

	function get_current_language() {
		global $wpml_request_handler, $wpml_language_resolution;

		if ( ! $this->this_lang ) {
			$this->set_this_lang( $wpml_request_handler->get_requested_lang() );
		}
		if ( ! $this->this_lang ) {
			$this->set_this_lang( $this->get_default_language() );
		}

		return $wpml_language_resolution->current_lang_filter( $this->this_lang, $wpml_request_handler );
	}

	public function switch_lang( $code = null, $cookie_lang = false ) {
		global $wpml_language_resolution, $wpml_request_handler;

		WPML_Non_Persistent_Cache::flush_group( [ 'WPML_String_Translation', 'icl_get_string_translations_by_id' ] );

		$pre_switch_language = $this->get_current_language();

		$this->original_language = $this->original_language === null
			? $pre_switch_language : $this->original_language;

		if ( is_null( $code ) ) {
			$frame = array_pop( $this->language_switch_stack );

			if ( null === $frame ) {
				$this->set_this_lang( $this->original_language );
				unset( $GLOBALS['icl_language_switched'] );
			} else {
				$this->set_this_lang( $frame['lang'] );
				if ( ! empty( $frame['cookie'] ) ) {
					$wpml_request_handler->set_language_cookie( $frame['cookie'] );
				}
				$this->restore_wpml_switch_language_triggered( $frame['switched'] );
			}
		} else {
			$writes_cookie = $cookie_lang && ! apply_filters( 'wpml_should_skip_saving_language_in_cookies', false );

			$this->language_switch_stack[] = array(
				'lang'     => $pre_switch_language,
				'cookie'   => $writes_cookie ? $wpml_request_handler->get_cookie_lang() : false,
				'switched' => $this->is_wpml_switch_language_triggered(),
			);

			if ( $code === 'all' || in_array( $code, $wpml_language_resolution->get_active_language_codes(), true ) ) {
				$this->set_this_lang( $code );
			}

			if ( $writes_cookie ) {
				$wpml_request_handler->set_language_cookie( $code );
			}

			if ( $code ) {
				$GLOBALS['icl_language_switched'] = true;
			} else {
				unset( $GLOBALS['icl_language_switched'] );
			}
		}

		do_action( 'wpml_language_has_switched', $code, $cookie_lang, $this->original_language );
	}

	public function restore_language_state_at_request_end() {
		foreach ( array_keys( $this->language_switch_stack ) as $frame ) {
			$this->language_switch_stack[ $frame ]['cookie'] = false;
		}

		while ( $this->is_wpml_switch_language_triggered() ) {
			$this->switch_lang( null );
		}
	}

	function set_default_language( $code ) {
		if ( null !== \WPML\LanguageEditor\LanguageDomains::demotedWithoutDomain( $this, $code ) ) {
			return false;
		}

		$previous_default = $this->get_setting( 'default_language' );
		$this->set_setting( 'default_language', $code );
		$this->set_setting( 'admin_default_language', $code );
		$this->save_settings();
		if ( $code !== $previous_default ) {
			$this->clear_language_name_cache();
		}

		do_action( 'icl_after_set_default_language', $code, $previous_default );

		$locale = $this->get_locale( $code );
		if ( $locale ) {
			update_option( 'WPLANG', 'en_US' === $locale ? '' : $locale );
		}

		$user_language = new WPML_User_Language( $this );
		$user_language->sync_default_admin_user_languages();

		return 'en_US' !== $locale && ! file_exists( WP_LANG_DIR . '/' . $locale . '.mo' ) ? 1 : true;
	}

	function register_assets() {
		global $wpdb, $wpml_post_translations, $wpml_term_translations;

		$page                  = \WPML\SuperGlobals\Request::page();
		$page                  = '' !== $page ? basename( $page ) : null;
		$page_basename         = $page === null ? '' : preg_replace(
			'/[^\w-]/',
			'',
			str_replace( '.php', '', $page )
		);
		$this->scripts_handler = new WPML_Admin_Scripts_Setup(
			$wpdb,
			$this,
			$wpml_post_translations,
			$wpml_term_translations,
			(string) $page_basename
		);

		$this->scripts_handler->register_styles();
	}

	function js_load() {
		global $pagenow, $wpdb, $wpml_post_translations, $wpml_term_translations;

		$page                  = \WPML\SuperGlobals\Request::page();
		$page                  = '' !== $page ? basename( $page ) : null;
		$page_basename         = $page === null ? '' : preg_replace(
			'/[^\w-]/',
			'',
			str_replace( '.php', '', $page )
		);
		$this->scripts_handler = new WPML_Admin_Scripts_Setup(
			$wpdb,
			$this,
			$wpml_post_translations,
			$wpml_term_translations,
			(string) $page_basename
		);

		if ( is_admin() && ! defined( 'DOING_AJAX' ) && ! wpml_is_rest_request() && empty( $_GET['legacy-widget-preview'] ) ) {

			$this->scripts_handler->add_admin_hooks();

			if ( $this->is_post_edit_script_allowed() ) {
				wp_register_script( 'sitepress-post-edit-tags', ICL_PLUGIN_URL . '/res/js/post-edit-terms.js', array( 'jquery' ), ICL_SITEPRESS_SCRIPT_VERSION );
				$post_edit_messages = array(
					/* translators: Question asked before the language of a post is changed. {post_name} is replaced with the title of the post and stays as it is. */
					'switch_language_title'   => __( 'You are about to change the language of {post_name}.', 'sitepress' ),
					'switch_language_alert'   => __( 'All categories and tags will be translated if possible.', 'sitepress' ),
					'connection_loss_alert'   => __( 'The following terms do not have a translation in the chosen language and will be disconnected from this post:', 'sitepress' ),
					/* translators: Text shown while the language details of a post are being loaded. {post_name} is replaced with the title of the post and stays as it is. */
					'loading'                 => __( 'Loading Language Data for {post_name}', 'sitepress' ),
					'switch_language_message' => __( 'Please make sure that you\'ve saved all the changes. We will have to reload the page.', 'sitepress' ),
					'switch_language_confirm' => __( 'Do you want to continue?', 'sitepress' ),
					'_nonce'                  => wp_create_nonce( 'wpml_switch_post_lang_nonce' ),
					'empty_post_title'        => __( '(No title for this post yet)', 'sitepress' ),
					/* translators: Button label that confirms a dialog and carries out what it asks about. */
					'ok_button_label'         => __( 'OK', 'sitepress' ),
					/* translators: Button label that closes a dialog without doing anything, or stops what is going on. Verb, imperative, not the noun "a cancellation". */
					'cancel_button_label'     => __( 'Cancel', 'sitepress' ),
					'_get_default_lang_nonce' => wp_create_nonce( 'wpml_get_default_lang' ),
				);
				wp_localize_script( 'sitepress-post-edit-tags', 'icl_post_edit_messages', $post_edit_messages );
				wp_enqueue_script( 'sitepress-post-edit-tags' );
			}

			if ( isset( $_SERVER['SCRIPT_NAME'] ) && strpos( $_SERVER['SCRIPT_NAME'], 'edit.php' ) ) {
				wp_register_script( 'sitepress-post-list-quickedit', ICL_PLUGIN_URL . '/res/js/post-list-quickedit.js', array( 'jquery' ), ICL_SITEPRESS_SCRIPT_VERSION );
				wp_enqueue_script( 'sitepress-post-list-quickedit' );
			}

			wp_register_script( 'wpml-purify', ICL_PLUGIN_URL . '/dist/js/domPurify/app.js', [], ICL_SITEPRESS_SCRIPT_VERSION );
			wp_register_script( 'sitepress-scripts', ICL_PLUGIN_URL . '/res/js/scripts.js', [ 'jquery', 'jquery-ui-dialog', 'wpml-purify' ], ICL_SITEPRESS_SCRIPT_VERSION );
			wp_localize_script(
                'sitepress-scripts',
                'wpml_core_strings',
                [
					/* translators: Label of the control that closes an open section or dialog. Verb, imperative, not the adjective meaning near. */
					'dialogCloseText' => __( 'Close', 'sitepress' ),
				]
            );
			wp_enqueue_script( 'sitepress-scripts' );

			if ( isset( $page_basename ) && file_exists( WPML_PLUGIN_PATH . '/res/js/' . $page_basename . '.js' ) ) {
				$dependencies         = array();
				$localization         = false;
				$color_picker_handler = 'wp-color-picker';
				switch ( $page_basename ) {
					case 'languages':
						$dependencies[] = $color_picker_handler;
						$dependencies[] = 'sitepress-scripts';
						$dependencies[] = 'wpml-domain-validation';
						$dependencies[] = 'jquery-ui-dialog';
						$dependencies[] = 'wpml-tooltip';
						break;
					case 'menus-sync':
						$localization = array(
							'object_name' => 'menus_sync',
							'strings'     => array(
								/* translators: Notice on the menu synchronization screen. %1$s: the opening tag of a link to the String Translation screen, %2$s: its closing tag. "WPML Translation Dashboard -> Other texts (Strings)" and "Translations -> Strings" are the names of screens as they appear in the WPML menu. */
								'text1'          => esc_html__( 'Your menu includes custom items, which may not be synchronized. Translate the menu items from the WPML Translation Dashboard -> Other texts (Strings) or manually from the %1$sTranslations -> Strings%2$s page.', 'sitepress' ),
								/* translators: Second line of that notice. "This" is the synchronization the reader is about to run. */
								'text2'          => esc_html__( "When you synchronize the menu, you're also running the menu sync operation. This will use the strings that you've translated to update the menus.", 'sitepress' ),
								// Management is loaded; under a blog license it is not, but
								'stringsUrl'     => add_query_arg( 'tab', 'strings', add_query_arg( 'page', urlencode( ( defined( 'WPML_TM_FOLDER' ) ? WPML_TM_FOLDER : 'tm' ) . '/menu/main.php' ), 'admin.php' ) ),
								'menusSyncNonce' => wp_create_nonce( 'wpml_get_links_for_menu_strings_translation' ),
								/* translators: Shown on the menu synchronization screen when applying the changes was refused and the server gave no reason of its own. */
								'syncFailedText' => esc_html__( 'These changes could not be synchronized. Please reload the page and try again.', 'sitepress' ),
							),
						);
						break;
				}
				$handle = 'sitepress-' . $page_basename;
				wp_register_script( $handle, ICL_PLUGIN_URL . '/res/js/' . $page_basename . '.js', $dependencies, ICL_SITEPRESS_SCRIPT_VERSION );
				if ( $localization ) {
					wp_localize_script( $handle, $localization['object_name'], $localization['strings'] );
				}
				if ( in_array( $color_picker_handler, $dependencies ) ) {
					wp_enqueue_style( $color_picker_handler );
				}
				wp_enqueue_script( $handle );
			}

			if ( $pagenow == 'edit.php' ) {
				add_action( 'admin_enqueue_scripts', array( $this, 'language_filter' ) );
			}

			wp_enqueue_style( 'wpml-select-2', ICL_PLUGIN_URL . '/lib/select2/select2.css', array(), ICL_SITEPRESS_SCRIPT_VERSION );

		}
	}

	private function is_post_edit_script_allowed() {
		return (
			isset( $_SERVER['SCRIPT_NAME'] ) &&
			( strpos( $_SERVER['SCRIPT_NAME'], 'post-new.php' ) || strpos( $_SERVER['SCRIPT_NAME'], 'post.php' ) ) ||
			apply_filters( 'wpml_enable_language_meta_box', false )
		);
	}

	function backend_js( $setup_complete = true ) {
		wp_register_script( 'sitepress', ICL_PLUGIN_URL . '/res/js/sitepress.js', [], ICL_SITEPRESS_SCRIPT_VERSION );
		wp_enqueue_script( 'sitepress' );

		$vars = [
			'restUrl'        => untrailingslashit( rest_url() ),
			'restNonce'      => wp_create_nonce( 'wp_rest' ),
			'loadLanguageJs' => ! ( defined( 'ICL_DONT_LOAD_LANGUAGES_JS' ) && ICL_DONT_LOAD_LANGUAGES_JS ),
		];
		if ( $setup_complete ) {
			$vars = array_merge(
				$vars,
				[
					'current_language' => $this->this_lang,
					'icl_home'         => $this->language_url(),
					'ajax_url'         => $this->convert_url( admin_url( 'admin-ajax.php' ), $this->this_lang ),
					'url_type'         => WPML_URL_Format::of( $this->settings ),
				]
			);
		} else {
			$vars = array_merge(
				$vars,
				[
					'current_language' => 'en',
					'icl_home'         => '',
				]
			);
		}

		wp_localize_script( 'sitepress', 'icl_vars', $vars );
	}

	function rtl_fix() {
		global $wp_styles, $wp_locale;

		if ( is_admin() ) {
			$direction = $this->rendered_admin_direction();
			if ( null === $direction ) {
				return;
			}
			if ( $wp_locale instanceof WP_Locale ) {
				$wp_locale->text_direction = $direction;
			}
			if ( ! empty( $wp_styles ) ) {
				$wp_styles->text_direction = $direction;
			}
			$GLOBALS['text_direction'] = $direction;
		} elseif ( ! empty( $wp_styles ) && $this->is_rtl() ) {
			$wp_styles->text_direction = 'rtl';
		}
	}

	public function rendered_admin_direction() {
		$locale = function_exists( 'get_user_locale' ) ? (string) get_user_locale() : (string) get_locale();
		if ( '' === $locale ) {
			return null;
		}

		$code = $this->get_language_code_from_locale( $locale, false );
		if ( ! $code ) {
			return null;
		}

		return \WPML\LanguageEditor\RtlLanguages::isRtl( (string) $code ) ? 'rtl' : 'ltr';
	}

	function post_edit_language_options() {
		global $post, $post_new_file, $post_type_object, $pagenow;

		if ( null === $post || ! $this->get_setting( 'setup_complete', false ) ) {
			return;
		}

		$is_preview    = ( isset( $_POST['wp-preview'] ) && $_POST['wp-preview'] === 'dopreview' ) || is_preview();
		$is_attachment = in_array( $pagenow, array( 'upload.php', 'media-upload.php' ), true )
						|| ( $post && 'attachment' === $post->post_type ) || is_attachment();

		if ( $is_attachment || $is_preview ) {
			return;
		}

		if ( current_user_can( 'manage_options' ) && \WPML\WP\OptionManager::getOr( false, 'core', 'show_cf_meta_box' ) ) {
			add_meta_box(
                'icl_div_config',
                __( 'Multilingual Content Setup', 'sitepress' ),
                array(
					$this,
					'meta_box_config',
                ),
                $post->post_type,
                'normal',
                'low'
			);
		}

		if ( filter_input( INPUT_POST, 'icl_action' ) === 'icl_mcs_inline'
			&& \WPML\Request\Policy\Registry::declare(
				\WPML\Request\Policy\Registry::PSEUDO_ROUTE,
				'icl_mcs_inline',
				\WPML\Request\Policy\Policy::capability(
					'manage_options',
					\WPML\Request\Policy\Authenticity::actionNonce( 'icl_mcs_inline_nonce', '_icl_nonce' )
				)
			)->permits()
		) {
			$custom_post_type = filter_input( INPUT_POST, 'custom_post' );
			$translate        = (int) filter_input( INPUT_POST, 'translate' );
			$was_translatable = ! empty( $this->settings['custom_posts_sync_option'][ $custom_post_type ] );
			$iclsettings['custom_posts_sync_option'][ $custom_post_type ] = $translate;
			if ( $translate ) {
				$this->verify_post_translations( $custom_post_type );
				if ( ! $was_translatable && function_exists( 'wpml_load_settings_helper' ) ) {
					wpml_load_settings_helper()->park_newly_translatable_in_tea(
						array( $custom_post_type ),
						$translate,
						\WPML\TM\ATE\TranslateEverything\ParkedTypesOffer::DOOR_INLINE
					);
				}
			}

			$custom_taxs_off  = (array) filter_input( INPUT_POST, 'custom_taxs_off', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );
			$custom_taxs_on   = (array) filter_input( INPUT_POST, 'custom_taxs_on', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );
			$tax_sync_options = array_merge( array_fill_keys( $custom_taxs_on, 1 ), array_fill_keys( $custom_taxs_off, 0 ) );
			foreach ( $tax_sync_options as $key => $setting ) {
				if ( $setting ) {
					wpml_load_settings_helper()->set_taxonomy_translatable_mode( $key, $setting );
				} else {
					$iclsettings['taxonomies_sync_option'][ $key ] = $setting;
				}
			}

			$cf_names         = (array) filter_input( INPUT_POST, 'cfnames', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );
			$cf_vals          = (array) filter_input( INPUT_POST, 'cfvals', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );
			$translations     = [];
			$original_post_id = null;

			if ( in_array( 1, $cf_vals, false ) ) {
				global $wpml_post_translations;
				$original_post_id = $wpml_post_translations->get_original_element( $post->ID, true );
				$translations     = array_diff( $wpml_post_translations->get_element_translations( $original_post_id ), array( $original_post_id ) );
			}

			$cf_modes = [];
			foreach ( $cf_names as $k => $v ) {
				$cf_modes[ base64_decode( $v ) ] = isset( $cf_vals[ $k ] ) ? (int) $cf_vals[ $k ] : 0;
			}
			PreferenceWriter::setModes( ElementType::POST, $cf_modes );

			foreach ( $cf_modes as $custom_field_name => $cf_translation_state ) {
				do_action( 'wpml_single_custom_field_sync_option_updated', array( $custom_field_name => $cf_translation_state ) );

				if ( $original_post_id && $translations && 1 === $cf_translation_state ) {
					foreach ( $translations as $translated_id ) {
						$this->sync_custom_field( $original_post_id, $translated_id, $custom_field_name );
					}
				}
			}

			$this->save_settings( $iclsettings );
		}

		$post_types = array_keys( $this->get_translatable_documents() );
		if ( in_array( $post->post_type, $post_types, true ) ) {
			add_meta_box(
				WPML_Meta_Boxes_Post_Edit_HTML::WRAPPER_ID,
				/* translators: Column heading and field label in the WPML admin, for the language of a piece of content. Noun, singular. */
				__( 'Language', 'sitepress' ),
				array(
					$this,
					'meta_box',
				),
				(string) $post->post_type,
				apply_filters( 'wpml_post_edit_meta_box_context', 'side', WPML_Meta_Boxes_Post_Edit_HTML::WRAPPER_ID ),
				apply_filters( 'wpml_post_edit_meta_box_priority', 'high' )
			);
		}

		if ( isset( $post_new_file, $post_type_object ) && $this->is_translated_post_type( $post_type_object->name ) ) {
			$post_language = $this->get_language_for_element( $post->ID, 'post_' . $post_type_object->name );
			$post_new_file = add_query_arg( array( 'lang' => $post_language ), $post_new_file );
		}
	}

	function set_element_language_details_action( $args ) {
		$element_id           = $args['element_id'];
		$element_type         = isset( $args['element_type'] ) ? $args['element_type'] : 'post_post';
		$trid                 = $args['trid'];
		$language_code        = $args['language_code'];
		$source_language_code = isset( $args['source_language_code'] ) ? $args['source_language_code'] : null;
		$check_duplicates     = isset( $args['check_duplicates'] ) ? $args['check_duplicates'] : true;
		$result               = $this->set_element_language_details( $element_id, $element_type, $trid, $language_code, $source_language_code, $check_duplicates );
		$args['result']       = $result;
	}

	public function set_element_language_details(
		$el_id,
		$el_type,
		$trid,
		$language_code,
		$src_language_code = null,
		$check_duplicates = true,
		$check_null = false
	) {
		$metadata_initializer    = null;
		$previous_source_post_id = 0;
		if (
			$el_id
			&& $trid
			&& 0 === strpos( $el_type, 'post_' )
			&& ! WPML_Post_Translation_Metadata_Initializer::is_suspended()
		) {
			$metadata_initializer    = $this->get_post_translation_metadata_initializer();
			$previous_source_post_id = $metadata_initializer->get_source_post_id( $el_id, $el_type );
		}

		if ( ! $el_id && class_exists( \WPML\TM\Jobs\JobLog::class ) ) {
			\WPML\TM\Jobs\JobLog::add(
                'set_element_language_details_null_el_id',
                [
					'el_id'                => $el_id,
					'el_type'              => $el_type,
					'trid'                 => $trid,
					'language_code'        => $language_code,
					'source_language_code' => $src_language_code,
					'check_duplicates'     => $check_duplicates,
					'check_null'           => $check_null,
				]
            );
		}

		if ( ! $this->language_setter ) {
			$this->language_setter = new WPML_Set_Language(
				$this,
				$this->wpdb,
				$this->post_translation,
				$this->term_translation
			);
		}

		$result = $this->language_setter->set(
			$el_id,
			$el_type,
			$trid,
			$language_code,
			$src_language_code,
			$check_duplicates,
			$check_null
		);

		if ( $result && $metadata_initializer ) {
			$metadata_initializer->initialize_if_source_changed(
				$el_id,
				$el_type,
				$previous_source_post_id
			);
		}

		return $result;
	}

	private function get_post_translation_metadata_initializer() {
		if ( ! $this->post_translation_metadata_initializer ) {
			$this->post_translation_metadata_initializer = new WPML_Post_Translation_Metadata_Initializer(
				$this,
				$this->wpdb,
				new WPML_Copy_Once_Custom_Field( $this, $this->post_translation )
			);
		}

		return $this->post_translation_metadata_initializer;
	}

	public function delete_orphan_element( $element_id, $element_type, $target_language ) {
		$this->delete_element_translation(
			$this->get_element_trid( $element_id, $element_type ),
			$element_type,
			$target_language,
			true
		);
	}

	function delete_element_translation( $trid, $element_type, $language_code = false, $orphan_translation_only = false ) {
		$result = false;

		if ( $trid !== false && is_numeric( $trid ) && $element_type !== false && is_string( $trid ) ) {
			$delete_where   = array(
				'trid'         => $trid,
				'element_type' => $element_type,
			);
			$delete_formats = array( '%d', '%s' );

			if ( $language_code ) {
				$delete_where['language_code'] = $language_code;
				$delete_formats[]              = '%s';
			}
			if ( $orphan_translation_only ) {
				$delete_where['element_id'] = null;
			}

			$context     = explode( '_', $element_type );
			$update_args = array(
				'trid'         => $trid,
				'element_type' => $element_type,
				'context'      => $context[0],
			);

			do_action( 'wpml_translation_update', array_merge( $update_args, array( 'type' => 'before_delete' ) ) );

			$result = WPML_Translation_Records_Delete::translations_by_columns( $delete_where, $delete_formats );

			do_action( 'wpml_translation_update', array_merge( $update_args, array( 'type' => 'after_delete' ) ) );

			$this->get_translations_cache()
				->clear();
		}

		return $result;
	}

	function get_element_language_details( $el_id, $el_type = 'post_post' ) {
		$details = false;
		if ( $el_id ) {
			if ( strpos( $el_type, 'post_' ) === 0 ) {
				$details = $this->post_translation->get_element_language_details( $el_id, OBJECT );
			}
			if ( strpos( $el_type, 'tax_' ) === 0 ) {
				$details = $this->term_translation->get_element_language_details( $el_id, OBJECT );
			}
			if ( ! $details ) {
				$cache_ref      = WPML_Set_Language::get_cache_ref( $el_id, $el_type );
				$cached_details = wp_cache_get( ...$cache_ref );
				if ( $cached_details ) {
					return $cached_details;
				}
				if ( $this->get_translations_cache()
							->has_key( $el_id . $el_type ) ) {
					return $this->get_translations_cache()
								->get( $el_id . $el_type );
				}
				$wpdb    = $this->wpdb;
				$details = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT trid, language_code, source_language_code
						FROM {$wpdb->prefix}icl_translations
						WHERE element_id=%d AND element_type=%s",
						$el_id,
						$el_type
					)
				);
				$this->get_translations_cache()
					->set( $el_id . $el_type, $details );

				wp_cache_add( ...$cache_ref );
			}
		}

		return $details;
	}

	public function sync_custom_field( $post_id_from, $post_id_to, $meta_key ) {
		$sync_custom_fields = new WPML_Sync_Custom_Fields(
			new WPML_Translation_Element_Factory( $this ),
			array( $meta_key )
		);
		$sync_custom_fields->sync_custom_field( $post_id_from, $post_id_to, $meta_key );
	}

	public function copy_custom_fields( $post_id_from, $post_id_to, $custom_fields_to_sync = null ) {
		$custom_fields_to_sync = is_array( $custom_fields_to_sync ) || null === $custom_fields_to_sync
			? $custom_fields_to_sync
			: null;
		if ( array() === $custom_fields_to_sync ) {
			return array();
		}

		$custom_fields_from = get_post_meta( $post_id_from );
		$custom_fields_from = is_array( $custom_fields_from ) ? $custom_fields_from : [];
		$custom_fields_to   = get_post_meta( $post_id_to );
		$custom_fields_to   = is_array( $custom_fields_to ) ? $custom_fields_to : [];

		$resolved_preferences = wpml_resolve_custom_field_preferences(
			array_merge( array_keys( $custom_fields_from ), array_keys( $custom_fields_to ) )
		);

		$custom_fields_to_copy = array_merge(
			$this->get_custom_fields_translation_settings( WPML_COPY_CUSTOM_FIELD ),
			array_keys( $resolved_preferences, WPML_COPY_CUSTOM_FIELD, true )
		);

		$copy_fields_to_process = $custom_fields_to_copy;
		if ( is_array( $custom_fields_to_sync ) ) {
			$changed_lookup         = array_fill_keys( $custom_fields_to_sync, true );
			$copy_fields_to_process = array_values(
				array_filter(
					$custom_fields_to_copy,
					function ( $meta_key ) use ( $changed_lookup ) {
						return isset( $changed_lookup[ $meta_key ] );
					}
				)
			);

			if ( ! $copy_fields_to_process && false === has_filter( 'wpml_sync_deleted_custom_fields' ) ) {
				return array();
			}
		}

		$processed_custom_fields = array();

		if ( null === $custom_fields_to_sync || $copy_fields_to_process ) {
			$sync_custom_fields = new WPML_Sync_Custom_Fields(
				new WPML_Translation_Element_Factory( $this ),
				$custom_fields_to_copy
			);
			if ( null === $custom_fields_to_sync ) {
				$processed_custom_fields = $sync_custom_fields->sync_custom_fields_batch(
					$post_id_from,
					$post_id_to,
					$custom_fields_from,
					$custom_fields_to
				);
			} else {
				$processed_custom_fields = $sync_custom_fields->sync_custom_fields_batch(
					$post_id_from,
					$post_id_to,
					$custom_fields_from,
					$custom_fields_to,
					$custom_fields_to_sync
				);
			}
		}

		$sync_deleted_fields = false;
		if ( apply_filters( 'wpml_sync_deleted_custom_fields', $sync_deleted_fields ) && $custom_fields_from && $custom_fields_to ) {
			$if_deleted_in_source_and_still_in_target = Logic::allPass(
				[
					Obj::prop( Fns::__, $custom_fields_to ),
					pipe( Obj::prop( Fns::__, $custom_fields_from ), Logic::not() ),
				]
			);

			$translate_fields = array_merge(
				$this->get_custom_fields_translation_settings( WPML_TRANSLATE_CUSTOM_FIELD ),
				array_keys( $resolved_preferences, WPML_TRANSLATE_CUSTOM_FIELD, true )
			);
			if ( is_array( $custom_fields_to_sync ) ) {
				$changed_lookup   = array_fill_keys( $custom_fields_to_sync, true );
				$translate_fields = array_values(
					array_filter(
						$translate_fields,
						function ( $meta_key ) use ( $changed_lookup ) {
							return isset( $changed_lookup[ $meta_key ] );
						}
					)
				);
			}

			wpml_collect( $translate_fields )
				->filter( $if_deleted_in_source_and_still_in_target )
				->map(
					function ( $meta_key ) use ( $post_id_from, $post_id_to ) {
						$this->sync_custom_field( $post_id_from, $post_id_to, $meta_key );
					}
				);
		}

		return is_array( $processed_custom_fields ) ? $processed_custom_fields : array();
	}

	public function get_custom_fields_translation_settings( $mode ) {
		return PreferenceResolver::namesByMode( ElementType::POST, (int) $mode );
	}

	function update_post_meta( $meta_id, $object_id, $meta_key, $_meta_value ) {
		return;
	}

	function delete_post_meta( $meta_id ) {
		return;
	}


	function get_element_translations_filter( $value, $trid, $el_type = 'post_post', $skip_empty = false, $all_statuses = false, $skip_cache = false ) {
		return $this->get_element_translations( $trid, $el_type, $skip_empty, $all_statuses, $skip_cache );
	}


	public function get_original_element_id_filter( $empty, $element_id, $element_type = 'post_post' ) {
		$original_element_id = $this->get_original_element_id( $element_id, $element_type );

		return $original_element_id;
	}

	public function get_element_trid_filter( $empty, $element_id, $element_type = 'post_post' ) {
		$trid = $this->get_element_trid( $element_id, $element_type );

		return $trid;
	}

	function is_original_content_filter( $default, $element_id, $element_type = 'post_post' ) {
		$is_original_content = $default;

		$trid = $this->get_element_trid( $element_id, $element_type );

		$translations = $this->get_element_translations( $trid, $element_type );

		if ( $translations ) {
			foreach ( $translations as $language_code => $translation ) {
				if ( $translation->element_id == $element_id ) {
					$is_original_content = $translation->original;
					break;
				}
			}
		}

		return $is_original_content;
	}

	function get_element_translations( $trid, $el_type = 'post_post', $skip_empty = false, $all_statuses = false, $skip_cache = false, $skip_recursions = false, $deprecated_skip_privilege_checking = false ) {
		$wpml_translations                  = new WPML_Translations( $this );
		$wpml_translations->skip_empty      = $skip_empty;
		$wpml_translations->all_statuses    = $all_statuses;
		$wpml_translations->skip_cache      = $skip_cache;
		$wpml_translations->skip_recursions = $skip_recursions;

		return $wpml_translations->get_translations( $trid, $el_type );
	}

	function clear_elements_cache( $ids, $taxonomy ) {
		$cache = new WPML_WP_Cache( WPML_ELEMENT_TRANSLATIONS_CACHE_GROUP );
		$cache->flush_group_cache();
	}

	static function get_original_element_id( $element_id, $element_type = 'post_post', $skip_empty = false, $all_statuses = false, $skip_cache = false, $deprecated_skip_privilege_checking = false ) {
		global $sitepress;

		$original_element_id = false;

		$trid = $sitepress->get_element_trid( $element_id, $element_type );
		if ( $trid ) {
			$original_element = $sitepress->get_original_element_translation( $trid, $element_type, $skip_empty, $all_statuses, $skip_cache );
			if ( $original_element ) {
				$original_element_id = $original_element->element_id;
			}
		}

		return $original_element_id;
	}

	public function get_original_element_translation( $trid, $element_type, $skip_empty = false, $all_statuses = false, $skip_cache = false, $deprecated_skip_privilege_checking = false ) {
		$visibility_scope = '';
		if ( 0 === strpos( (string) $element_type, 'post_' ) && 'post_attachment' !== $element_type ) {
			if ( \WPML\Core\Security\ExecutionContext\ExecutionContextHolder::isTrusted() ) {
				$visibility_scope = 'trusted';
			} elseif ( ! $all_statuses && ! is_admin() ) {
				$user_id          = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
				$visibility_scope = $user_id ? 'user:' . $user_id : 'anonymous';
			} else {
				$visibility_scope = 'unfiltered';
			}
		}
		$cache_key_args = array( $trid, $element_type, $skip_empty, $all_statuses, $visibility_scope );
		$cache_key      = md5( (string) wp_json_encode( $cache_key_args ) );
		$cache_group    = 'original_element';

		$original_element = null;
		if ( ! $skip_cache ) {
			$original_element = wp_cache_get( $cache_key, $cache_group );
		}

		if ( $original_element ) {
			return $original_element;
		}

		$element_translations = $this->get_element_translations( $trid, $element_type, $skip_empty, $all_statuses, $skip_cache );

		$original_element = null;
		foreach ( $element_translations as $element_translation ) {
			if ( $element_translation->original ) {
				$original_element = $element_translation;
				break;
			}
		}

		if ( $original_element ) {
			wp_cache_set( $cache_key, $original_element, $cache_group );
		}

		return $original_element;
	}

	function get_element_trid( $element_id, $el_type = 'post_post' ) {
		$wpdb = $this->wpdb;

		if ( strpos( $el_type, 'tax_' ) === 0 ) {
			global $wpml_term_translations;

			return $wpml_term_translations->get_element_trid( $element_id );
		} elseif ( strpos( $el_type, 'post_' ) === 0 ) {
			global $wpml_post_translations;

			return $wpml_post_translations->get_element_trid( $element_id );
		} else {
			$cache_key   = $element_id . ':' . $el_type;
			$cache_group = 'element_trid';
			$temp_trid   = wp_cache_get( $cache_key, $cache_group );
			if ( (bool) $temp_trid === true ) {
				return $temp_trid;
			}

			$trid = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id=%d AND element_type=%s",
					$element_id,
					$el_type
				)
			);

			if ( $trid ) {
				wp_cache_add( $cache_key, $trid, $cache_group );
			}
		}

		return $trid;
	}

	static function get_original_element_id_by_trid( $trid ) {
		global $wpdb;

		if ( (bool) $trid === true ) {
			$element_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT element_id
					 FROM {$wpdb->prefix}icl_translations
					 WHERE trid=%d
					  AND source_language_code IS NULL
					 LIMIT 1",
					$trid
				)
			);
		} else {
			$element_id = false;
		}

		return $element_id;
	}

	public static function register_non_persistent_cache_groups() {
		wp_cache_add_non_persistent_groups( 'get_source_language_by_trid' );
	}

	static function get_source_language_by_trid( $trid ) {
		if ( ! $trid ) {
			return null;
		}

		$trid = (int) $trid;

		return ( new WPML_WP_Cache( 'get_source_language_by_trid' ) )->execute_and_cache(
			serialize( [ $trid ] ),
			function () use ( $trid ) {
				return Obj::prop( 'language_code', \WPML\Records\Translations::getSourceByTrid( $trid ) );
			}
		);
	}

	public static function clear_source_language_by_trid_cache( $trid ) {
		( new WPML_WP_Cache( 'get_source_language_by_trid' ) )->delete( serialize( [ (int) $trid ] ) );
	}

	public function get_element_translations_object( $element_type ) {
		global $wpml_post_translations, $wpml_term_translations, $wpml_cache_factory;

		$element_translations = null;
		if ( strpos( $element_type, 'tax_' ) === 0 ) {
			$element_translations = $wpml_term_translations;
		} elseif ( strpos( $element_type, 'post_' ) === 0 ) {
			$element_translations = $wpml_post_translations;
		} else {
			$element_translations = new WPML_Element_Type_Translation( $this->wpdb, $wpml_cache_factory, $element_type );
		}

		return $element_translations;
	}

	function get_language_for_element( $element_id, $element_type = 'post_post' ) {
		$translation_object = $this->get_element_translations_object( $element_type );

		return $translation_object->get_element_lang_code( $element_id );
	}

	function get_elements_without_translations( $el_type, $target_lang, $source_lang ) {
		$wpdb = $this->wpdb;

		$trids_for_target = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT trid
				FROM {$wpdb->prefix}icl_translations
				WHERE language_code = %s
					AND element_type = %s",
				$target_lang,
				$el_type
			)
		);
		$trids_for_target = array_map( 'intval', $trids_for_target );
		$is_post_type     = 0 === strpos( $el_type, 'post_' );

		if ( $trids_for_target && $is_post_type ) {
			return $wpdb->get_col(
				$wpdb->prepare(
					"SELECT element_id
					FROM {$wpdb->prefix}icl_translations
					JOIN {$wpdb->posts}
						ON {$wpdb->posts}.ID = {$wpdb->prefix}icl_translations.element_id
					WHERE language_code = %s
						AND trid NOT IN (" . implode( ', ', array_fill( 0, count( $trids_for_target ), '%d' ) ) . ")
						AND element_type = %s
						AND {$wpdb->posts}.post_status NOT IN ('trash', 'auto-draft')",
					array_merge( array( $source_lang ), $trids_for_target, array( $el_type ) )
				)
			);
		}

		if ( $trids_for_target ) {
			return $wpdb->get_col(
				$wpdb->prepare(
					"SELECT element_id
					FROM {$wpdb->prefix}icl_translations
					WHERE language_code = %s
						AND trid NOT IN (" . implode( ', ', array_fill( 0, count( $trids_for_target ), '%d' ) ) . ")
						AND element_type = %s",
					array_merge( array( $source_lang ), $trids_for_target, array( $el_type ) )
				)
			);
		}

		if ( $is_post_type ) {
			return $wpdb->get_col(
				$wpdb->prepare(
					"SELECT element_id
					FROM {$wpdb->prefix}icl_translations
					JOIN {$wpdb->posts}
						ON {$wpdb->posts}.ID = {$wpdb->prefix}icl_translations.element_id
					WHERE language_code = %s
						AND element_type = %s
						AND {$wpdb->posts}.post_status NOT IN ('trash', 'auto-draft')",
					$source_lang,
					$el_type
				)
			);
		}

		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT element_id
				FROM {$wpdb->prefix}icl_translations
				WHERE language_code = %s
					AND element_type = %s",
				$source_lang,
				$el_type
			)
		);
	}

	function get_posts_without_translations( $selected_language, $default_language, $post_type = 'post_post' ) {
		$wpdb = $this->wpdb;

		$untranslated_ids = $this->get_elements_without_translations( $post_type, $selected_language, $default_language );
		$untranslated     = array();
		foreach ( $untranslated_ids as $id ) {
			$untranslated[ $id ] = $wpdb->get_var(
				$wpdb->prepare( "SELECT post_title FROM {$wpdb->prefix}posts WHERE ID = %d", $id )
			);
		}

		return $untranslated;
	}

	public function get_orphan_translations( $trid, $post_type, $source_language, $restrict_to_current_user_readable = false ) {
		if ( ! in_array( $source_language, array_keys( $this->get_active_languages() ), true ) ) {
			return array();
		}
		$element_type = 'post_' . $post_type;
		$members = array_filter(
			(array) $this->get_element_translations( $trid, $element_type ),
			function ( $translation ) use ( $element_type ) {
				return ! isset( $translation->element_type ) || $translation->element_type === $element_type;
			}
		);
		if ( count( $members ) !== 1 ) {
			return array();
		}

		$candidates = ( new \WPML\Translation\OrphanTranslationsRepository( $this->wpdb ) )
			->getOrphanCandidates( $element_type, $source_language );

		return \WPML\Translation\AuthorizedOrphanTranslations::build(
			$candidates,
			(bool) $restrict_to_current_user_readable
		);
	}

	public function has_orphan_translations( $post_type, $source_language ) {
		if ( ! in_array( $source_language, array_keys( $this->get_active_languages() ), true ) ) {
			return false;
		}
		return ( new \WPML\Translation\OrphanTranslationsRepository( $this->wpdb ) )
			->hasOrphans( 'post_' . $post_type, $source_language );
	}

	function meta_box( $post ) {

		$post = apply_filters( 'wpml_meta_box_post', $post );

		$post_edit_metabox = new WPML_Meta_Boxes_Post_Edit_HTML( $this, $this->post_translation );
		$post_edit_metabox->render_languages( $post );
		do_action( 'wpml_post_edit_languages', $post );
	}

	function meta_box_config( $post ) {
		global $iclTranslationManagement, $wp_taxonomies, $wp_post_types, $sitepress_settings;
		if ( empty( $this->settings['setup_complete'] ) ) {
			return;
		}

		$translation_modes = new WPML_Translation_Modes();

		/* translators: Notice shown after the language switcher settings are saved. */
		echo '<div class="icl_form_success" style="display:none">' . __( 'Settings saved', 'sitepress' ) . '</div>';

		$cp_editable      = true;
		$radio_disabled   = '';
		$translation_mode = WPML_CONTENT_TYPE_DONT_TRANSLATE;

		if ( isset( $sitepress_settings['custom_posts_sync_option'][ $post->post_type ] ) ) {
			$translation_mode = (int) $sitepress_settings['custom_posts_sync_option'][ $post->post_type ];
		}

		$read_only_translation_mode = null;
		if ( array_key_exists( $post->post_type, $iclTranslationManagement->settings['custom-types_readonly_config'] ) ) {
			$read_only_translation_mode = (int) $iclTranslationManagement->settings['custom-types_readonly_config'][ $post->post_type ];
		}

		$unlocked = false;
		if ( isset( $sitepress_settings['custom_posts_unlocked_option'][ $post->post_type ] ) ) {
			$unlocked = (bool) $sitepress_settings['custom_posts_unlocked_option'][ $post->post_type ];
		}

		if ( null !== $read_only_translation_mode && ! $unlocked ) {
			if ( array_key_exists( $post->post_type, $this->get_translatable_documents() ) ) {
				$translation_mode = $read_only_translation_mode;
				$radio_disabled   = 'disabled="disabled"';
			}
			$cp_editable = false;
		}

		echo '<ul>';

		foreach ( $translation_modes->get_options_for_post_type( $wp_post_types[ $post->post_type ]->labels->name ) as $value => $label ) {
			$checked = checked( $value, $translation_mode, false );

			$disabled_state_for_mode = WPML_Custom_Types_Translation_UI::get_disabled_state_for_mode(
				$unlocked,
				(bool) $radio_disabled,
				$value,
				$post->post_type
			);

			if ( $disabled_state_for_mode['reason_message'] ) {
				$label .= ' - ' . $disabled_state_for_mode['reason_message'];
			}
			$input_value = esc_attr( $post->post_type ) . ',' . esc_attr( $value );
			echo '<li>';
			echo '<label>';
			echo '<input id="icl_make_translatable' . $input_value . '" name="icl_make_translatable" type="radio" value="' . $input_value . '" ' . $checked . $disabled_state_for_mode['html_attribute'] . '/>';
			echo '&nbsp;' . wp_kses_post( $label );
			echo '</label>';
			echo '</li>';
		}
		echo '</ul>';

		echo '<br clear="all" /><span id="icl_mcs_details">';
		if ( $translation_modes->is_translatable_mode( $translation_mode ) ) {
			$custom_taxonomies = array_diff( get_object_taxonomies( $post->post_type ), array( 'post_tag', 'category', 'nav_menu', 'link_category', 'post_format' ) );
			if ( ! empty( $custom_taxonomies ) ) {
				?>
				<table class="widefat">
					<thead>
					<tr>
						<?php /* translators: Heading above the list of custom taxonomies on the screen where a content type is set up. */ ?>
						<th colspan="2"><?php _e( 'Custom taxonomies', 'sitepress' ); ?></th>
					</tr>
					</thead>
					<tbody>
					<?php foreach ( $custom_taxonomies as $ctax ) : ?>
						<?php
						$checked_tax_translate = ! empty( $sitepress_settings['taxonomies_sync_option'][ $ctax ] ) ? ' checked="checked"' : '';
						$checked_do_nothing    = empty( $sitepress_settings['taxonomies_sync_option'][ $ctax ] ) ? ' checked="checked"' : '';
						$radio_disabled        = isset( $iclTranslationManagement->settings['taxonomies_readonly_config'][ $ctax ] ) ? ' disabled="disabled"' : '';
						?>
						<tr>
							<td><?php echo $wp_taxonomies[ $ctax ]->labels->name; ?></td>
							<td align="right">
								<label><input name="icl_mcs_custom_taxs_<?php echo $ctax; ?>" class="icl_mcs_custom_taxs" type="radio"
												<?php /* translators: Option in the dropdown that says what happens to a field, and the heading of the column of the post editing screen where a translation is started: the text is translated. Verb, imperative. */ ?>
												value="<?php echo $ctax; ?>" <?php echo $checked_tax_translate; ?><?php echo $radio_disabled; ?> />&nbsp;<?php _e( 'Translate', 'sitepress' ); ?></label>
								<?php /* translators: Option that leaves a taxonomy as it is, on the screen where a content type is set up. Verb phrase, imperative. */ ?>
								<label><input name="icl_mcs_custom_taxs_<?php echo $ctax; ?>" type="radio" value="0" <?php echo $checked_do_nothing; ?><?php echo $radio_disabled; ?> />&nbsp;<?php _e( 'Do nothing', 'sitepress' ); ?></label>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<br/>
				<?php
			}

			if ( $this->get_wp_api()
						->constant( 'WPML_TM_VERSION' ) ) {
				$settings_factory                     = $iclTranslationManagement->settings_factory();
				$settings_factory->show_system_fields = array_key_exists( 'show_system_fields', $_GET ) ? (bool) $_GET['show_system_fields'] : false;

				?>
				<p>
					<?php
					$toggle_system_fields = array(
						'url'  => add_query_arg( array( 'show_system_fields' => ! $settings_factory->show_system_fields ) ),
						'text' => $settings_factory->show_system_fields ? __( 'Hide system fields', 'sitepress' ) : __( 'Show system fields', 'sitepress' ),
					);
					?>
					<a href="<?php echo esc_url( $toggle_system_fields['url'] ); ?>"><?php echo $toggle_system_fields['text']; ?></a>
				</p>
				<?php

				$settings_menu = new WPML_TM_Post_Edit_Custom_Field_Settings_Menu( $settings_factory, $post );
				echo $settings_menu->render();
				$custom_keys = $settings_menu->is_rendered();
			}

			if ( ! empty( $custom_taxonomies ) || ! empty( $custom_keys ) ) {
				echo '<small>' . __( 'Note: Custom taxonomies and custom fields are shared across different post types.', 'sitepress' ) . '</small>';
			}
		}
		echo '</span>';
		if ( $cp_editable || ! empty( $custom_taxonomies ) || ! empty( $custom_keys ) ) {
			/* translators: Button label next to a dropdown: carry out the chosen action. Verb, imperative. */
			echo '<p class="submit" style="margin:0;padding:0"><input class="button-secondary" id="icl_make_translatable_submit" type="button" value="' . __( 'Apply', 'sitepress' ) . '" /></p><br clear="all" />';
			wp_nonce_field( 'icl_mcs_inline_nonce', '_icl_nonce_imi' );
		} else {
			_e( 'Nothing to configure.', 'sitepress' );
		}
	}

	function pre_get_posts( $wpq ) {
		$post_action = isset( $_POST['action'] ) ? $_POST['action'] : null;
		if ( 'wp-link-ajax' === $post_action ) {
			global $wpml_language_resolution;
			$lang = $wpml_language_resolution->get_referrer_language_code();
			$this->set_this_lang( $lang );
			$wpq->query_vars['suppress_filters'] = false;
		}

		return $wpq;
	}

	function comment_feed_join( $join ) {
		$wpdb = $this->wpdb;

		global $wp_query;
		$type = isset( $wp_query->query_vars['post_type'] ) && $wp_query->query_vars['post_type'] ? esc_sql( $wp_query->query_vars['post_type'] ) : 'post';

		$wp_query->query_vars['is_comment_feed'] = true;
		$join                                   .= $this->wpdb->prepare(
			" JOIN {$wpdb->prefix}icl_translations wpml_translations
                                    ON {$wpdb->comments}.comment_post_ID = wpml_translations.element_id
                                        AND wpml_translations.element_type = %s AND wpml_translations.language_code = %s ",
			'post_' . $type,
			$this->this_lang
		);

		return $join;
	}

	function comments_clauses( $clauses, $obj ) {
		global $wpml_query_filter;

		return $wpml_query_filter->comments_clauses_filter( $clauses, $obj );
	}

	function language_filter() {
		require_once WPML_PLUGIN_PATH . '/menu/post-menus/wpml-post-language-filter.class.php';
		$post_lang_filter = new WPML_Post_Language_Filter( $this->wpdb, $this );
		$post_lang_filter->register_scripts();

		return $post_lang_filter->post_language_filter();
	}

	function exclude_other_language_pages2( $arr, $get_page_arguments ) {
		global $wpdb;
		$post_hooks = new WPML_Remove_Pages_Not_In_Current_Language( $wpdb, $this );

		return $post_hooks->filter_pages( $arr, $get_page_arguments );
	}

	function wp_dropdown_pages( $output ) {
		$wpdb = $this->wpdb;

		if ( isset( $_POST['lang_switch'] ) ) {
			$post_id = esc_sql( $_POST['lang_switch'] );
			$lang    = esc_sql( strip_tags( $_GET['lang'] ) );
			$parent  = $wpdb->get_var( $wpdb->prepare( "SELECT post_parent FROM {$wpdb->posts} WHERE ID=%d", $post_id ) );
			if ( $parent ) {
				global $wpml_post_translations;
				$trid                 = $wpml_post_translations->get_element_trid( $parent );
				$translated_parent_id = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT element_id
                                                                         FROM {$wpdb->prefix}icl_translations
                                                                         WHERE trid=%d
                                                                          AND element_type='post_page'
                                                                          AND language_code=%s",
						$trid,
						$lang
					)
				);
				if ( $translated_parent_id ) {
					$output = str_replace( 'selected="selected"', '', $output );
					$output = str_replace( 'value="' . $translated_parent_id . '"', 'value="' . $translated_parent_id . '" selected="selected"', $output );
				}
			}
		} elseif ( isset( $_GET['lang'] ) && isset( $_GET['trid'] ) ) {
			$lang                 = esc_sql( strip_tags( $_GET['lang'] ) );
			$trid                 = esc_sql( $_GET['trid'] );
			$post_type            = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'page';
			$elements_id          = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT element_id FROM {$wpdb->prefix}icl_translations
				 WHERE trid=%d AND element_type=%s AND element_id IS NOT NULL",
					$trid,
					'post_' . $post_type
				)
			);
			$translated_parent_id = 0;
			foreach ( $elements_id as $element_id ) {
				$parent               = $wpdb->get_var( $wpdb->prepare( "SELECT post_parent FROM {$wpdb->posts} WHERE ID=%d", $element_id ) );
				$trid                 = $wpdb->get_var(
					$wpdb->prepare(
						"
					SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id=%d AND element_type=%s",
						$parent,
						'post_' . $post_type
					)
				);
				$translated_parent_id = $wpdb->get_var(
					$wpdb->prepare(
						"
					SELECT element_id FROM {$wpdb->prefix}icl_translations
					WHERE trid=%d AND element_type=%s AND language_code=%s",
						$trid,
						'post_' . $post_type,
						$lang
					)
				);
				if ( $translated_parent_id ) {
					break;
				}
			}
			if ( $translated_parent_id ) {
				$output = str_replace( 'selected="selected"', '', $output );
				$output = str_replace( 'value="' . $translated_parent_id . '"', 'value="' . $translated_parent_id . '" selected="selected"', $output );
			}
		}
		if ( ! $output ) {
			$output = '<select id="parent_id"><option value="">' . __( 'Main Page (no parent)', 'sitepress' ) . '</option></select>';
		}

		return $output;
	}

	function add_translate_options( $trid, $active_languages, $selected_language, $translations, $type ) {
		if ( $trid && $this->wp_api->is_term_edit_page() ) :
			if ( empty( $this->settings['setup_complete'] ) ) {
				return;
			}
			?>

            <div id="icl_translate_options">

				<?php
				$translations_found = 0;
				$untranslated_found = 0;
				foreach ( $active_languages as $lang ) {
					if ( $selected_language == $lang['code'] ) {
						continue;
					}
					if ( isset( $translations[ $lang['code'] ]->element_id ) ) {
						$translations_found += 1;
					} else {
						$untranslated_found += 1;
					}
				}
				?>

				<?php if ( $untranslated_found > 0 ) : ?>

					<table cellspacing="1" class="icl_translations_table" style="min-width:200px;margin-top:10px;">
						<thead>
						<tr>
							<th colspan="2" style="padding:4px;background-color:#DFDFDF"><b><?php /* translators: Option in the dropdown that says what happens to a field, and the heading of the column of the post editing screen where a translation is started: the text is translated. Verb, imperative. */ esc_html_e( 'Translate', 'sitepress' ); ?></b></th>
						</tr>
						</thead>
						<tbody>
						<?php
						foreach ( $active_languages as $lang ) :
							if ( $selected_language == $lang['code'] ) {
								continue;
							}
							?>
							<tr>
								<?php if ( ! isset( $translations[ $lang['code'] ]->element_id ) ) : ?>
									<td style="padding:4px;line-height:normal;"><?php echo esc_html( $lang['display_name'] ); ?></td>
									<?php
									$taxonomy    = $_GET['taxonomy'];
									$post_type_q = isset( $_GET['post_type'] ) ? '&amp;post_type=' . esc_html( $_GET['post_type'] ) : '';
									$add_link    = admin_url( 'edit-tags.php?taxonomy=' . esc_html( $taxonomy ) . '&amp;trid=' . $trid . '&amp;lang=' . esc_attr( $lang['code'] ) . '&amp;source_lang=' . esc_attr( $selected_language ) . $post_type_q );
									?>
									<?php /* translators: Link in the table of translations on the post editing screen that starts a translation in that language. Verb, imperative, written in lower case as the table shows it. */ ?>
									<td style="padding:4px;line-height:normal;"><a href="<?php echo $add_link; ?>"><?php echo esc_html__( 'add', 'sitepress' ); ?></a></td>
								<?php endif; ?>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>

				<?php if ( $translations_found > 0 ) : ?>
					<p style="clear:both;margin:5px 0 5px 0">
						<b><?php /* translators: Name of the screen where content is sent for translation and translations are managed: in the WPML menu, as the title of that screen, and as the text of links that open it. Plural noun. */ esc_html_e( 'Translations', 'sitepress' ); ?></b>
						(<a class="icl_toggle_show_translations" href="#"
							<?php
							if ( empty( $this->settings['show_translations_flag'] ) ) :
								?>
								style="display:none;"<?php endif; ?>><?php /* translators: Link text on the post editing screen that folds the list of translations away. It sits in brackets after the list and starts in lower case. Verb, imperative. */ esc_html_e( 'hide', 'sitepress' ); ?></a><a
							class="icl_toggle_show_translations" href="#"
							<?php
							if ( ! empty( $this->settings['show_translations_flag'] ) ) :
								?>
								style="display:none;"<?php endif; ?>><?php /* translators: Link text on the post editing screen that unfolds the list of translations. It sits in brackets after the list and starts in lower case. Verb, imperative. */ esc_html_e( 'show', 'sitepress' ); ?></a>)

						<?php wp_nonce_field( 'toggle_show_translations_nonce', '_icl_nonce_tst' ); ?>
					<table cellspacing="1" width="100%" id="icl_translations_table" style="
					<?php
					if ( empty( $this->settings['show_translations_flag'] ) ) :
						?>
						display:none;<?php endif; ?>margin-left:0;">

						<?php
						foreach ( $active_languages as $lang ) :
							if ( $selected_language === $lang['code'] ) {
								continue;
							}
							?>
							<tr>
								<?php if ( isset( $translations[ $lang['code'] ]->element_id ) ) : ?>
									<td style="line-height:normal;"><?php echo esc_html( $lang['display_name'] ); ?></td>
									<?php
									$taxonomy  = $_GET['taxonomy'];
									$edit_link = get_edit_term_link( $translations[ $lang['code'] ]->term_id, $taxonomy, isset( $_GET['post_type'] ) ? $_GET['post_type'] : null );
									?>
									<td align="right" width="30%"
										style="line-height:normal;"><?php echo isset( $translations[ $lang['code'] ]->name ) ? '<a href="' . esc_url( $edit_link ) . '" title="' . /* translators: Link text that opens something for changing: a date, a language or a translation. Verb, imperative. */ esc_attr__( 'Edit', 'sitepress' ) . '">' . esc_html( $translations[ $lang['code'] ]->name ) . '</a>' : /* translators: Shown in a table cell in place of a value that is not there. Keep the short form your language uses for "not available". */ esc_html__( 'n/a', 'sitepress' ); ?></td>

								<?php endif; ?>
							</tr>
						<?php endforeach; ?>
					</table>
				<?php endif; ?>
				<br clear="all" style="line-height:1px;"/>
				<?php
				do_action(
					'wpml_translate_options_terms',
					array(
						'trid'              => $trid,
						'active_languages'  => $active_languages,
						'selected_language' => $selected_language,
						'translations'      => $translations,
						'type'              => $type,
					)
				);
				?>
			</div>
			<?php
		endif;
	}

	function the_category_name_filter( $name ) {
		if ( is_array( $name ) ) {
			foreach ( $name as $k => $v ) {
				$name[ $k ] = $this->the_category_name_filter( $v );
			}

			return $name;
		}
		if ( false === strpos( $name, '@' ) ) {
			return $name;
		}
		if ( false !== strpos( $name, '<a' ) ) {
			$int = preg_match_all( '|<a([^>]+)>([^<]+)</a>|i', $name, $matches );
			if ( $int && count( $matches[0] ) > 1 ) {
				$originals = $filtered = array();
				foreach ( $matches[0] as $m ) {
					$originals[] = $m;
					$filtered[]  = $this->the_category_name_filter( $m );
				}
				$name = str_replace( $originals, $filtered, $name );
			} else {
				$name_sh = strip_tags( $name );
				$exp     = explode( '@', $name_sh );
				$name    = str_replace( $name_sh, trim( $exp[0] ), $name );
			}
		} else {
			$name = preg_replace( '#(.*) @(.*)#i', '$1', $name );
		}

		return $name;
	}

	function get_terms_filter( $terms ) {
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}
		foreach ( $terms as $k => $v ) {
			if ( isset( $terms[ $k ]->name ) ) {
				$terms[ $k ]->name = $this->the_category_name_filter( $terms[ $k ]->name );
			}
		}

		return $terms;
	}

	function create_term( $cat_id, $tt_id, $taxonomy ) {
		$term_actions = $this->get_term_actions_helper();
		$term_actions->save_term_actions( $tt_id, $taxonomy );
	}

	function deleted_term_relationships( $post_id, $delete_terms, $taxonomy ) {
		if ( $this->get_setting( 'sync_post_taxonomies' ) ) {
			$term_actions = $this->get_term_actions_helper();
			$term_actions->deleted_term_relationships( $post_id, $delete_terms, $taxonomy );
		}
	}

	function delete_term( $cat, $tt_id, $taxonomy ) {
		$term_actions = $this->get_term_actions_helper();
		$term_actions->delete_term_actions( $tt_id, $taxonomy );
	}

	public function get_term_actions_helper() {
		if ( ! isset( $this->term_actions ) ) {
			global $wpml_term_translations, $wpml_post_translations;
			$this->term_actions = new WPML_Term_Actions(
				$this,
				$this->wpdb,
				$wpml_post_translations,
				$wpml_term_translations
			);
		}

		return $this->term_actions;
	}

	function get_terms_args_filter( $args, $taxonomies ) {
		if ( ! $this->term_query_filter ) {
			global $wpml_term_translations;

			$this->term_query_filter = new WPML_Term_Query_Filter(
				$wpml_term_translations,
				new WPML_Debug_BackTrace( null, 5 ),
				$this->get_wpdb(),
				$this,
				new WPML_Term_Query_Opt_Out( $this )
			);
		}

		$this->term_query_filter->set_lang( $this->get_current_language(), $this->get_default_language() );

		return $this->term_query_filter->get_terms_args_filter( $args, $taxonomies );
	}

	public function clear_term_query_filter() {
		$this->term_query_filter = null;
	}

	function terms_clauses( $clauses, $taxonomies, $args ) {

		$term_clauses = new WPML_Term_Clauses(
			$this,
			$this->wpdb,
			new WPML_Display_As_Translated_Taxonomy_Query( $this->wpdb, 'tt' ),
			new WPML_Debug_BackTrace( null, 10 )
		);

		return $term_clauses->filter( $clauses, $taxonomies, $args );
	}

	public function set_wp_query() {
		global $wp_query;

		if ( 'wp' === $this->get_wp_api()
							->current_action() || ! $this->get_wp_api()
														->did_action( 'wp' ) ) {
			$this->wp_query = is_object( $wp_query ) ? clone $wp_query : null;
		}
	}

	public function get_wp_query() {
		return $this->wp_query;
	}

	function convert_url( $url, $code = null ) {
		global $wpml_url_converter;

		return $wpml_url_converter->convert_url( $url, $code );
	}

	function convert_url_string( $url, $code ) {
		global $wpml_url_converter;

		return $wpml_url_converter->get_strategy()
									->convert_url_string( $url, $code );
	}

	function language_url( $code = null, $forceSlashedBaseUrl = false ) {
		global $wpml_url_converter;

		if ( is_null( $code ) ) {
			$code = $this->this_lang;
		}

		$abs_home = $forceSlashedBaseUrl ? trailingslashit( $wpml_url_converter->get_abs_home() ) : $wpml_url_converter->get_abs_home();
		$url      = $this->convert_url( $abs_home, $code );

		return $url;
	}

	function post_type_archive_link_filter( $link, $post_type ) {
		global $wpml_url_converter;

		if ( isset( $this->settings['custom_posts_sync_option'][ $post_type ] ) && $this->settings['custom_posts_sync_option'][ $post_type ] ) {
			$link = $wpml_url_converter->convert_url( $link );
			$link = $this->adjust_cpt_in_url( $link, $post_type );
		}

		return $link;
	}

	public function adjust_cpt_in_url( $link, $post_type, $language_code = null ) {

		if ( $this->cpt_slug_translation_turned_on( $post_type ) ) {
			$url_cpt_converter = new WPML_URL_Converter_CPT();
			$link              = $url_cpt_converter->adjust_cpt_slug_in_url( $link, $post_type, $language_code );
		}

		return $link;
	}

	private function cpt_slug_translation_turned_on( $post_type ) {
		return isset( $this->settings['posts_slug_translation']['types'][ $post_type ] )
				&& $this->settings['posts_slug_translation']['types'][ $post_type ]
				&& get_option( 'wpml_base_slug_translation' );
	}

	function home_url( $url ) {

		return $url;
	}

	function get_comment_link_filter( $link ) {
		$link = html_entity_decode( $link );

		return $link;
	}

	public function get_query_utils() {

		return new WPML_Query_Utils( $this->wpdb, $this->wp_api, array_keys( $this->get_display_as_translated_documents() ) );
	}

	public function get_root_page_utils() {

		return wpml_get_root_page_actions_obj();
	}

	public function get_wp_api() {
		$this->wp_api = $this->wp_api ? $this->wp_api : new WPML_WP_API();
		return $this->wp_api;
	}

	public function &wpdb() {

		return $this->wpdb;
	}

	public function &core_tm() {
		global $iclTranslationManagement;

		return $iclTranslationManagement;
	}

	function &term_translations() {

		return $this->term_translation;
	}

	function &post_translations() {

		return $this->post_translation;
	}

	public function set_wp_api( $wp_api ) {
		$this->wp_api = $wp_api;
	}

	public function get_ls_languages( $template_args = array() ) {

		global $wp_query, $wpml_post_translations, $wpml_term_translations, $wpml_url_converter;

		$this->set_wp_query();

		$current_language = $this->get_current_language();
		$default_language = $this->get_default_language();

		$cache        = new WPML_LS_Languages_Cache( $template_args, $current_language, $default_language, $this->wp_query );
		$ls_languages = $cache->get();
		if ( $ls_languages ) {
			return $ls_languages;
		}

		if ( ! isset( $wp_query ) ) {
			return apply_filters( 'wpml_active_languages_access', $this->get_active_languages(), array( 'action' => 'read' ) );
		}

		$ls_languages_status = WPML_Get_LS_Languages_Status::get_instance();
		$ls_languages_status->start();

		$is_root_request = WPML_Root_Page::is_configured_root_request();

		$caller_query = $wp_query;
		$caller_post  = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;

		$_wp_query_back = clone $wp_query;
		unset( $wp_query );
		global $wp_query;
		if ( is_object( $this->wp_query ) ) {
			$wp_query = clone $this->wp_query;
		} else {
			$this->wp_query = clone $wp_query;
		}

		$w_active_languages = apply_filters( 'wpml_active_languages_access', $this->get_active_languages(), array( 'action' => 'read' ) );

		if ( isset( $template_args['skip_missing'] ) ) {
			$icl_lso_link_empty = ! $template_args['skip_missing'];
		} else {
			$icl_lso_link_empty = $this->settings['icl_lso_link_empty'];
		}

		$link_empty_to = ! empty( $template_args['link_empty_to'] ) ? $template_args['link_empty_to'] : null;

		$languages_helper = new WPML_Languages( $wpml_term_translations, $this, $wpml_post_translations );
		list( $translations, $wp_query ) = $languages_helper->get_ls_translations(
			$wp_query,
			$_wp_query_back,
			$this->wp_query
		);

		$display_as_translated_ls_link = new WPML_LS_Display_As_Translated_Link(
			$this,
			$wpml_url_converter->get_strategy(),
			$this->wp_query,
			new WPML_Translation_Element_Factory( $this )
		);

		$languages_with_followable_url = array();
		foreach ( $w_active_languages as $k => $lang ) {
			$skip_lang = false;
			if ( $is_root_request ) {
				$lang['translated_url'] = $this->language_url( $lang['code'], true );
			} elseif ( is_singular()
				|| ( isset( $_wp_query_back->query['name'] ) && isset( $_wp_query_back->query['post_type'] ) )
				|| $this->is_page_query()
			) {
				$this->switch_lang( $lang['code'] );
				$lang_page_on_front  = get_option( 'page_on_front' );
				$lang_page_for_posts = get_option( 'page_for_posts' );
				if ( $lang_page_on_front ) {
					$lang_page_on_front = icl_object_id( $lang_page_on_front, 'page', false, $lang['code'] );
				}
				if ( $lang_page_for_posts ) {
					$lang_page_for_posts = icl_object_id( $lang_page_for_posts, 'page', false, $lang['code'] );
				}
				if ( 'page' === get_option( 'show_on_front' ) && ! empty( $translations[ $lang['code'] ] ) && $translations[ $lang['code'] ]->element_id == $lang_page_on_front ) {
					$lang['translated_url'] = $this->language_url( $lang['code'], true );
				} elseif ( 'page' == get_option( 'show_on_front' ) && ! empty( $translations[ $lang['code'] ] ) && $translations[ $lang['code'] ]->element_id && $translations[ $lang['code'] ]->element_id == $lang_page_for_posts ) {
					if ( $lang_page_for_posts ) {
						$lang['translated_url'] = get_permalink( $lang_page_for_posts );
					} else {
						$lang['translated_url'] = $this->language_url( $lang['code'], true );
					}
				} elseif ( ! empty( $translations[ $lang['code'] ] ) && isset( $translations[ $lang['code'] ]->post_title ) ) {
						$lang['translated_url'] = get_permalink( $translations[ $lang['code'] ]->element_id );
						$lang['missing']        = 0;
				} else {
					$translated_url = $display_as_translated_ls_link->get_url( $translations, $lang['code'] );
					if ( $translated_url ) {
						$lang['translated_url'] = $translated_url;

						$languages_with_followable_url[ $lang['code'] ] = true;
					} elseif ( $icl_lso_link_empty ) {
						if ( ! empty( $link_empty_to ) ) {
							$lang['translated_url'] = str_replace( '{%lang}', $lang['code'], $link_empty_to );
						} else {
							$lang['translated_url'] = $this->language_url( $lang['code'], true );
						}
					} else {
						$skip_lang = true;
					}
					$lang['missing'] = 1;
				}
				$this->switch_lang();
			} elseif ( is_category() || is_tax() || is_tag() ) {
				global $icl_adjust_id_url_filter_off;

				$icl_adjust_id_url_filter_off = true;

				list( $lang, $skip_lang ) = $languages_helper->add_tax_url_to_ls_lang(
					$lang,
					$translations,
					$icl_lso_link_empty,
					$skip_lang,
					$link_empty_to,
					$display_as_translated_ls_link
				);

				$icl_adjust_id_url_filter_off = false;
			} elseif ( is_author() ) {
				global $authordata;
				if ( empty( $authordata ) ) {
					$authordata = get_userdata( get_query_var( 'author' ) );
				}
				remove_filter( 'home_url', array( $this, 'home_url' ), 1 );
				remove_filter( 'author_link', array( $this, 'author_link' ) );
				list( $lang, $skip_lang ) = $languages_helper->add_author_url_to_ls_lang(
					$lang,
					$default_language,
					$authordata,
					$icl_lso_link_empty,
					$skip_lang,
					$link_empty_to
				);
				add_filter( 'home_url', array( $this, 'home_url' ), 1, 4 );
				add_filter( 'author_link', array( $this, 'author_link' ) );
			} elseif ( is_archive() && ! is_tag() ) {
				global $icl_archive_url_filter_off;
				$icl_archive_url_filter_off = true;
				remove_filter( 'post_type_archive_link', array( $this, 'post_type_archive_link_filter' ), 10 );
				list( $lang, $skip_lang ) = $languages_helper->add_date_or_cpt_url_to_ls_lang(
					$lang,
					$default_language,
					$this->wp_query,
					$icl_lso_link_empty,
					$skip_lang,
					$link_empty_to
				);
				add_filter( 'post_type_archive_link', array( $this, 'post_type_archive_link_filter' ), 10, 2 );
				$icl_archive_url_filter_off = false;
			} elseif ( is_search() ) {
				$url_glue               = strpos( $this->language_url( $lang['code'] ), '?' ) === false ? '?' : '&';
				$search_query           = get_query_var( 's' );
				$search_query           = is_scalar( $search_query ) ? urlencode( (string) $search_query ) : '';
				$lang['translated_url'] = $this->language_url( $lang['code'], true ) . $url_glue . 's=' . $search_query;
			} elseif ( $icl_lso_link_empty || is_home() || is_404() || ( 'page' === get_option( 'show_on_front' ) && ( $this->wp_query->queried_object_id == get_option( 'page_on_front' ) || $this->wp_query->queried_object_id == get_option( 'page_for_posts' ) ) ) ) {
					$lang['translated_url'] = $this->language_url( $lang['code'], true );
					$skip_lang              = false;
			} else {
				$skip_lang = true;
				unset( $w_active_languages[ $k ] );
			}
			if ( ! $skip_lang ) {
				$w_active_languages[ $k ] = $lang;
			} else {
				unset( $w_active_languages[ $k ] );
			}
		}

		foreach ( $w_active_languages as $k => $v ) {
			$w_active_languages[ $k ] = $languages_helper->get_ls_language(
				$k,
				$current_language,
				$w_active_languages[ $k ]
			);
		}

		$parameters_copied = apply_filters(
			'icl_lang_sel_copy_parameters',
			array_map(
				'trim',
				explode(
					',',
					wpml_get_setting_filter(
						'',
						'icl_lang_sel_copy_parameters'
					)
				)
			)
		);
		if ( $parameters_copied ) {
			foreach ( $_GET as $k => $v ) {
				if ( in_array( $k, $parameters_copied, true ) ) {
					$gets_passed[ $k ] = wp_unslash( $v );
				}
			}
		}
		if ( ! empty( $gets_passed ) ) {
			$gets_passed = PreservedArguments::encode( $gets_passed );
			foreach ( $w_active_languages as $code => $al ) {
				if ( empty( $al['missing'] ) || isset( $languages_with_followable_url[ $code ] ) ) {
					$w_active_languages[ $code ]['url'] = add_query_arg( $gets_passed, $w_active_languages[ $code ]['url'] );
				}
			}
		}

		unset( $wp_query );
		global $wp_query;
		$wp_query = $caller_query;
		unset( $_wp_query_back );

		$w_active_languages = apply_filters( 'icl_ls_languages', $w_active_languages );

		$w_active_languages = $languages_helper->sort_ls_languages( $w_active_languages, $template_args );

		if ( $this->get_setting( 'language_negotiation_type' ) == WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN ) {
			foreach ( $w_active_languages as $lang => $element ) {
				$w_active_languages[ $lang ]['url'] = $this->convert_url( $element['url'], $lang );
			}
		}

		$w_active_languages = $this->maybeHideLanguages( $w_active_languages );

		$this->restore_caller_loop_globals( $caller_query, $caller_post );

		$cache->set( $w_active_languages );

		$ls_languages_status->end();

		return $w_active_languages;
	}

	private function restore_caller_loop_globals( $query, $post ) {
		$GLOBALS['wp_query'] = $query;
		$GLOBALS['post'] = $post;
		if ( is_object( $post ) ) {
			setup_postdata( $post );
		}
	}

	function get_display_single_language_name_filter( $empty, $args ) {
		$language_code = $args['language_code'];
		$display_code  = isset( $args['display_code'] ) ? $args['display_code'] : null;

		return $this->get_display_language_name( $language_code, $display_code );
	}

	function get_display_language_name( $lang_code, $display_code = null ) {
		$wpdb = $this->wpdb;

		$display_code = $display_code ? $display_code : $this->get_current_language();
		$display_code = 'all' === $display_code ? $this->get_admin_language() : $display_code;

		$source_key  = is_scalar( $lang_code ) ? (string) $lang_code : '';
		$display_key = is_scalar( $display_code ) ? (string) $display_code : '';
		$cache_key   = $source_key . $display_key;
		$cache       = $this->get_language_name_cache();
		$use_shard   = false;

		if ( $source_key !== $display_key ) {
			$supported_codes = $this->get_supported_language_codes();
			$use_shard       = in_array( $source_key, $supported_codes, true )
				&& in_array( $display_key, $supported_codes, true );
		}

		$translated_name = $use_shard
			? $cache->get_from_shard( $display_key, $cache_key )
			: $cache->get( $cache_key );
		if ( ! $translated_name ) {
			$translated_name = $wpdb->get_var(
				$wpdb->prepare(
					"  SELECT name
                       FROM {$wpdb->prefix}icl_languages_translations
                       WHERE language_code=%s
                        AND display_language_code=%s",
					$lang_code,
					$display_code
				)
			);
			if ( $use_shard ) {
				$cache->set_in_shard( $display_key, $cache_key, $translated_name );
			} else {
				$cache->set( $cache_key, $translated_name );
			}
		}

		return $translated_name;
	}

	function get_flag( $lang_code ) {
		return $this->flags->get_flag( $lang_code );
	}

	function get_flag_url( $code ) {
		return $this->flags->get_flag_url( $code );
	}

	function get_flag_img( $code ) {
		return $this->get_flag_image( $code );
	}

	function get_flag_image( $code, $size = [], $fallback_text = '', $css_classes = [] ) {
		return $this->flags->get_flag_image( $code, $size, $fallback_text, $css_classes );
	}


	function clear_flags_cache() {
		$this->flags->clear();
	}

	function get_desktop_language_selector() {
		return $this->get_language_selector();
	}

	function get_mobile_language_selector() {
		return $this->get_language_selector();
	}

	function get_language_selector() {
		ob_start();
		do_action( 'wpml_add_language_selector' );
		$output = ob_get_contents();
		ob_end_clean();

		return $output;
	}

	function language_selector() {
		do_action( 'wpml_add_language_selector' );
	}

	public function add_extra_debug_info( $extra_debug ) {
		$extra_debug['WPML(setup)'] = get_option( 'WPML(setup)' );

		$settings = $this->get_settings();
		unset( $settings['site_key'] );

		$extra_debug['WPML'] = $settings;

		return $extra_debug;
	}

	function set_default_categories( $def_cat ) {
		$this->settings['default_categories'] = $def_cat;
		\WPML\TaxonomyTermTranslation\DefaultCategoryDeleteProtection::resetProtectedTermIds();
		$this->save_settings();
	}

	function pre_option_default_category( $setting ) {
		$wpdb = $this->wpdb;

		$deleting = ( new \WPML\TaxonomyTermTranslation\TermDeleteInFlight() )->language( 'category', $this );
		if ( $deleting ) {
			$lang = $deleting;
		} elseif ( \WPML\TaxonomyTermTranslation\WritingScreenCategoryControls::isWritingScreenRequest() ) {
			$lang = $this->get_default_language();
		} else {
			$lang = filter_input( INPUT_POST, 'icl_post_language', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$lang = $lang ? $lang : filter_input( INPUT_GET, 'lang', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$lang = $lang ? $lang : $this->get_current_language();
		}

		$lang = $lang === 'all' ? $this->get_default_language() : $lang;
		$ttid = isset( $this->settings['default_categories'][ $lang ] ) ? (int) $this->settings['default_categories'][ $lang ] : 0;

		return $ttid === 0
			? null : $wpdb->get_var(
				$wpdb->prepare(
					"SELECT term_id
		                     FROM {$wpdb->term_taxonomy}
		                     WHERE term_taxonomy_id= %d
		                     AND taxonomy='category'",
					$ttid
				)
			);
	}

	public function pre_update_option_default_category( $value, $old_value ) {
		$comparable = ( is_scalar( $value ) || null === $value ) && ( is_scalar( $old_value ) || null === $old_value );

		if ( $comparable && (string) $value === (string) $old_value ) {
			return $old_value;
		}

		if ( \WPML\TaxonomyTermTranslation\WritingScreenCategoryControls::isWritingScreenRequest()
			&& ! $this->is_default_language_category( $value ) ) {
			return $old_value;
		}

		return $value;
	}

	private function category_ttid_of_term_id( $term_id ) {
		$term_id = is_scalar( $term_id ) ? (int) $term_id : 0;

		if ( $term_id <= 0 ) {
			return 0;
		}

		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'category' AND term_id = %d",
				$term_id
			)
		);
	}

	private function is_default_language_category( $term_id ) {
		$term_taxonomy_id = $this->category_ttid_of_term_id( $term_id );

		if ( $term_taxonomy_id <= 0 ) {
			return false;
		}

		$details = $this->get_element_language_details( $term_taxonomy_id, 'tax_category' );

		return isset( $details->language_code )
			&& (string) $details->language_code === (string) $this->get_default_language();
	}

	function update_option_default_category( $oldvalue, $new_value ) {
		$wpdb = $this->wpdb;

		$new_value = $wpdb->get_var( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy='category' AND term_id=%d", $new_value ) );

		if ( \WPML\TaxonomyTermTranslation\WritingScreenCategoryControls::isWritingScreenRequest() ) {
			if ( $new_value ) {
				$default_categories = (array) $this->get_setting( 'default_categories', array() );

				$default_categories[ $this->get_default_language() ] = (int) $new_value;

				$this->set_default_categories( $default_categories );
			}

			return;
		}

		$translations = $new_value ? $this->get_element_translations( $this->get_element_trid( (int) $new_value, 'tax_category' ), 'tax_category' ) : [];
		if ( ! empty( $translations ) ) {
			$icl_settings = [];
			foreach ( $translations as $t ) {
				$icl_settings['default_categories'][ $t->language_code ] = $t->element_id;
			}
			if ( $icl_settings ) {
				$this->save_settings( $icl_settings );
				\WPML\TaxonomyTermTranslation\DefaultCategoryDeleteProtection::resetProtectedTermIds();
			}
		}
	}


	public function get_term_adjust_id( $term ) {
		global $icl_adjust_id_url_filter_off, $wpml_term_translations, $wpml_post_translations;

		if ( ! $this->wpml_term_adjust_id ) {
			$this->wpml_term_adjust_id = new WPML_Term_Adjust_Id(
				new WPML_Debug_BackTrace( null, 15, true ),
				$wpml_term_translations,
				$wpml_post_translations,
				$this
			);
			foreach ( [ 'wpml_translation_update', 'clean_term_cache', 'created_term', 'edited_term', 'delete_term' ] as $action ) {
				add_action( $action, [ $this->wpml_term_adjust_id, 'forget_translations' ] );
			}
		}

		return $this->wpml_term_adjust_id->filter(
			$term,
			apply_filters( 'wpml_disable_term_adjust_id', $icl_adjust_id_url_filter_off, $term )
		);
	}

	public function edited_term_action() {
		WPML_Non_Persistent_Cache::flush_group( [ 'WPML_Term_Adjust_Id', 'WPML_Term_Translation' ] );
	}

	function get_pages_adjust_ids( $pages, $args ) {
		if ( $pages && $this->get_current_language() !== $this->get_default_language() ) {
			$args_hash       = md5( (string) wp_json_encode( $args ) );
			$cache_key_args  = md5( (string) wp_json_encode( wp_list_pluck( $pages, 'ID' ) ) );
			$cache_key_args .= ':';
			$cache_key_args .= $args_hash;

			$cache_key     = $cache_key_args;
			$cache_group   = 'get_pages_adjust_ids';
			$found         = false;
			$cached_result = wp_cache_get( $cache_key, $cache_group, false, $found );

			if ( ! $found ) {
				if ( $args['include'] ) {
					$args = $this->translate_csv_page_ids( $args, 'include' );
				}
				if ( $args['exclude'] ) {
					$args = $this->translate_csv_page_ids( $args, 'exclude' );
				}
				if ( $args['child_of'] ) {
					$args['child_of'] = icl_object_id( $args['child_of'], 'page', true );
				}
				if ( md5( (string) wp_json_encode( $args ) ) !== $args_hash ) {
					remove_filter( 'get_pages', array( $this, 'get_pages_adjust_ids' ), 1 );
					$pages = get_pages( $args );
					add_filter( 'get_pages', array( $this, 'get_pages_adjust_ids' ), 1, 2 );
				}
				wp_cache_set( $cache_key, $pages, $cache_group );
			} else {
				$pages = $cached_result;
			}
		}

		return $pages;
	}

	private function translate_csv_page_ids( $args, $index ) {
		$translated_ids = array();
		if ( array_key_exists( $index, $args ) ) {
			$original_ids = $args[ $index ];
			if ( ! is_array( $args[ $index ] ) ) {
				$original_ids = array_map( 'trim', explode( ',', $args[ $index ] ) );
			}
			foreach ( $original_ids as $i ) {
				$t = icl_object_id( $i, 'page', true );
				if ( $t ) {
					$translated_ids[] = $t;
				}
			}
		}
		$args[ $index ] = implode( ',', $translated_ids );

		return $args;
	}

	function feed_link( $out ) {
		return $this->convert_url( $out );
	}

	function post_comments_feed_link( $out ) {
		if ( WPML_LANGUAGE_NEGOTIATION_TYPE_PARAMETER === WPML_URL_Format::of( $this->settings ) ) {
			$out = preg_replace( '@(\?|&)lang=([^/]+)/feed/@i', 'feed/$1lang=$2', $out );
		}

		return $out;
	}

	function trackback_url( $out ) {
		return $this->convert_url( $out );
	}

	function user_trailingslashit( $string, $type_of_url ) {
		if ( $type_of_url == 'comment' ) {
			$string = preg_replace( '@(.*)/\?lang=([a-z-]+)/(.*)@is', '$1/$3?lang=$2', $string );
		}

		return $string;
	}

	function author_link( $url ) {
		$url = $this->convert_url( $url );
		return is_string( $url )
			? preg_replace( '#^http://(.+)//(.+)$#', 'http://$1/$2', $url )
			: '';
	}

	function pre_option_home( $setting = false ) {
		if ( ! defined( 'TEMPLATEPATH' ) ) {
			return $setting;
		}

		if ( null === $this->template_real_path ) {
			$this->template_real_path = realpath( TEMPLATEPATH );
		}

		if ( false === $this->template_real_path ) {
			return $setting;
		}

		$debug_backtrace   = $this->get_backtrace( 7 );
		$function          = isset( $debug_backtrace[4] ) && isset( $debug_backtrace[4]['function'] ) ? $debug_backtrace[4]['function'] : null;
		$previous_function = isset( $debug_backtrace[5] ) && isset( $debug_backtrace[5]['function'] ) ? $debug_backtrace[5]['function'] : null;
		$inc_methods       = array( 'include', 'include_once', 'require', 'require_once' );

		if ( $function === 'get_bloginfo' && $previous_function === 'bloginfo' ) {
			$is_template_file = false !== strpos( $debug_backtrace[5]['file'], (string) $this->template_real_path );
			$is_direct_call   = in_array( $debug_backtrace[6]['function'], $inc_methods ) || ( false !== strpos( $debug_backtrace[6]['file'], (string) $this->template_real_path ) );
		} elseif ( in_array( $function, array( 'get_bloginfo', 'get_settings' ), true ) ) {
			$is_template_file = false !== strpos( $debug_backtrace[4]['file'], (string) $this->template_real_path );
			$is_direct_call   = in_array( $previous_function, $inc_methods ) || ( false !== strpos( $debug_backtrace[5]['file'], (string) $this->template_real_path ) );
		} else {
			$is_template_file = isset( $debug_backtrace[3]['file'] ) && ( false !== strpos( $debug_backtrace[3]['file'], (string) $this->template_real_path ) );
			$is_direct_call   = in_array( $function, $inc_methods )
								|| (
									isset( $debug_backtrace[4]['file'] )
									&& false !== strpos( $debug_backtrace[4]['file'], (string) $this->template_real_path )
								);
		}

		$home_url = $is_template_file && $is_direct_call ? $this->language_url( $this->this_lang ) : $setting;

		return $home_url;
	}

	function query_vars( $public_query_vars ) {
		global $wp_query;

		if ( WPML_LANGUAGE_NEGOTIATION_TYPE_PARAMETER === (int) $this->get_setting( 'language_negotiation_type' ) ) {
			$public_query_vars[]          = 'lang';
			$wp_query->query_vars['lang'] = $this->this_lang;
		}

		return $public_query_vars;
	}

	function parse_query( $q ) {
		global $wpml_query_filter;

		$query_parser = new WPML_Query_Parser( $this, $wpml_query_filter );

		return $query_parser->parse_query( $q );
	}

	function adjust_wp_list_pages_excludes( $pages ) {
		foreach ( $pages as $k => $v ) {
			$pages[ $k ] = icl_object_id( $v, 'page', true );
		}

		return $pages;
	}

	function language_attributes( $output ) {
		return preg_replace(
			'#lang="(.*?)"#',
			'lang="' . str_replace( '_', '-', $this->this_lang ) . '"',
			$output
		);
	}

	function plugin_localization() {
		load_plugin_textdomain( 'sitepress', false, WPML_PLUGIN_FOLDER . '/locale' );
	}

	public function get_wpml_locale() {
		return $this->locale_utils;
	}

	public function pre_determine_locale_filter( $locale ) {
		if ( null !== $locale
			 || $this->in_determine_locale
			 || ! $this->is_admin_originated_rest_request()
			 || WPML_Locale::is_core_locale_switched()
		) {
			return $locale;
		}

		$this->in_determine_locale = true;
		$user_locale               = get_user_locale();
		$this->in_determine_locale = false;

		return $user_locale;
	}

	function locale_filter( $default ) {

		if ( ! $this->get_settings() ) {
			return $default;
		}

		$locale = $this->locale_utils->locale();

		return false === $locale ? $default : $locale;
	}

	function get_language_tag( $code ) {
		if ( is_null( $code ) ) {
			return false;
		}

		$tags = wp_cache_get( 'icl_language_tags' );

		if ( ! is_array( $tags ) || ! array_key_exists( $code, $tags ) ) {
			$tags = array();
			$all_tags_data = $this->wpdb->get_results( "SELECT code, tag FROM {$this->wpdb->prefix}icl_languages" );
			foreach ( $all_tags_data as $tag_data ) {
				$tags[ $tag_data->code ] = $tag_data->tag;
			}

			wp_cache_set( 'icl_language_tags', $tags );
		}

		if ( ! array_key_exists( $code, $tags ) ) {
			return false;
		}

		if ( $tags[ $code ] ) {
			return (string) $tags[ $code ];
		}

		$locale = $this->get_locale( $code );

		return $locale ? (string) $locale : (string) $code;
	}

	function get_locale( $code ) {

		return $this->locale_utils->get_locale( $code );
	}

	function switch_locale( $lang_code = false ) {
		$this->locale_utils->switch_locale( $lang_code );
	}

	function get_locale_file_names() {

		return $this->locale_utils->get_locale_file_names();
	}

	function pre_option_page_on_front() {
		global $switched;

		$pre_option_page = new WPML_Pre_Option_Page( $this->wpdb, $this, $switched, $this->this_lang );

		return $pre_option_page->get_skipping_trashed( 'page_on_front' );
	}

	function pre_option_page_for_posts() {

		global $switched;

		$pre_option_page = new WPML_Pre_Option_Page( $this->wpdb, $this, $switched, $this->this_lang );

		return $pre_option_page->get_skipping_trashed( 'page_for_posts' );
	}

	function pre_option_wp_page_for_privacy_policy() {
		global $switched;

		$pre_option_page = new WPML_Pre_Option_Page( $this->wpdb, $this, $switched, $this->this_lang );

		return $pre_option_page->get_skipping_trashed( 'wp_page_for_privacy_policy' );
	}

	public function add_page_options_core_reset_hooks() {
		add_action( 'wp_trash_post', array( $this, 'page_options_answer_in_default_language' ), 9 );
		add_action( 'wp_trash_post', array( 'WPML_Pre_Option_Page', 'end_core_reset' ), 11 );
		add_action( 'before_delete_post', array( $this, 'page_options_answer_in_default_language' ), 9 );
		add_action( 'before_delete_post', array( 'WPML_Pre_Option_Page', 'end_core_reset' ), 11 );
	}

	function page_options_answer_in_default_language() {
		WPML_Pre_Option_Page::answer_in_language_during_core_reset( $this->get_default_language() );
	}

	function fix_trashed_front_or_posts_page_settings( $post_id ) {
		global $switched;

		$pre_option_page_current = new WPML_Pre_Option_Page( $this->wpdb, $this, $switched, $this->this_lang );
		$pre_option_page_current->fix_trashed_front_or_posts_page_settings( $post_id );
	}

	function restrict_manage_posts() {
		echo '<input type="hidden" name="lang" value="' . esc_attr( $this->this_lang ) . '" />';
	}

	function get_edit_term_link( $link, $term_id, $taxonomy, $object_type ) {
		global $wpml_term_translations;
		$default_language = $this->get_default_language();
		$current_language = $this->get_current_language();
		$lang             = $wpml_term_translations->lang_code_by_termid( $term_id );
		$lang             = $lang ? $lang : $default_language;

		if ( $lang !== $default_language || $current_language !== $default_language ) {
			$link .= '&lang=' . $lang;
		}

		return $link;
	}

	function noscript_notice() {
		?>
		<noscript>
			<div class="error"><?php echo __( 'WPML admin screens require JavaScript in order to display. JavaScript is currently off in your browser.', 'sitepress' ); ?></div>
		</noscript>
		<?php
	}

	function save_user_options() {
		$user_id = $_POST['user_id'];
		if ( $user_id ) {
			$verify_nonce = isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'update-user_' . $user_id );
			if ( isset( $_POST['icl_field_hidden_languages'] ) && $verify_nonce ) {
				update_user_meta( $user_id, 'icl_show_hidden_languages', isset( $_POST['icl_show_hidden_languages'] ) ? (int) $_POST['icl_show_hidden_languages'] : 0 );
			}

			$this->reset_admin_language_cookie();
		}
	}

	function help_admin_notice() {
		$args = array(
			'name' => 'wpml-intro',
			'iso'  => defined( 'WPLANG' ) ? WPLANG : '',
		);
		$q    = http_build_query( $args );
		?>
		<div id="message" class="notice wpml-notice otgs-is-dismissible">
			<p>
				<?php _e( 'You need to configure WPML before you can start translating.', 'sitepress' ); ?>
			</p>
			<p>
				<input type="hidden" id="icl_dismiss_help_nonce" value="<?php echo $icl_dhn = wp_create_nonce( 'dismiss_help_nonce' ); ?>"/>
				<?php /* translators: Heading of the screen where WPML is set up, and the text of the link that opens it. Verb phrase, imperative. */ ?>
				<a href="admin.php?page=<?php echo WPML_PLUGIN_FOLDER . '/menu/setup.php'; ?>" class="button-primary configure-wpml"><?php _e( 'Configure WPML', 'sitepress' ); ?></a>&nbsp;
				<?php
				$getting_started_url = \WPML\OutboundLinks\OutboundLinks::to(
					'https://wpml.org/documentation/getting-started-guide/',
					array(
						'medium'   => 'support',
						'campaign' => 'getting-started',
					)
				);
				?>
				<a href="<?php echo esc_url( $getting_started_url ); ?>" target="_blank">
					<?php _e( 'Getting started guide', 'sitepress' ); ?>
				</a>
			</p>
			<span title="<?php esc_attr_e( 'Stop showing this message', 'sitepress' ); ?>" id="icl_dismiss_help" class="notice-dismiss"><span class="screen-reader-text"><?php /* translators: Button label that closes a notice and keeps it from coming back. Verb, imperative. */ esc_html_e( 'Dismiss', 'sitepress' ); ?></span></span>
		</div>
		<?php
	}

	function display_wpml_footer() {
		if ( $this->get_setting( 'promote_wpml', false ) ) {
			$wpml_site_languages = array( 'es', 'de', 'fr', 'pt-br', 'ja', 'ru', 'zh-hans', 'it', 'he', 'ar' );
			$url_language_code   = in_array( ICL_LANGUAGE_CODE, $wpml_site_languages ) ? ICL_LANGUAGE_CODE . '/' : '';

			$part_one = _x( 'Multilingual WordPress', 'Multilingual WordPress with WPML: first part', 'sitepress' );
			$part_two = _x( 'with WPML', 'Multilingual WordPress with WPML: second part', 'sitepress' );

			$credit_url = \WPML\OutboundLinks\OutboundLinks::to(
				'https://wpml.org/' . $url_language_code,
				array(
					'medium'   => 'credit',
					'campaign' => 'credit-footer',
				)
			);
			echo '<p id="wpml_credit_footer"><a href="' . esc_url( $credit_url ) . '" rel="nofollow" >' . esc_html( $part_one ) . '</a> ' . esc_html( $part_two ) . '</p>';
		}
	}

	function xmlrpc_methods( $methods ) {
		return \WPML\Request\Adapter\XmlRpc::method(
			$methods,
			'translationproxy.get_languages_list',
			\WPML\Request\Policy\Policy::publicAccess(
				'deprecated Translation Proxy probe: returns the active language list, which the public site already exposes; no state change'
			),
			array( $this, 'xmlrpc_get_languages_list' )
		);
	}

	function xmlrpc_call_actions( $action ) {
		$params = icl_xml2array( print_r( file_get_contents( 'php://input' ), true ) );
		add_filter( 'is_protected_meta', array( $this, 'xml_unprotect_wpml_meta' ), 10, 3 );
		switch ( $action ) {
			case 'wp.getPage':
			case 'blogger.getPost':
				if ( isset( $params['methodCall']['params']['param'][1]['value']['int']['value'] ) ) {
					$page_id      = (int) filter_var( $params['methodCall']['params']['param'][1]['value']['int']['value'], FILTER_SANITIZE_NUMBER_INT );
					$lang_details = $this->get_element_language_details( $page_id, 'post_' . get_post_type( $page_id ) );
					$this->set_this_lang( $lang_details->language_code );
					update_post_meta( $page_id, '_wpml_language', $lang_details->language_code );
					update_post_meta( $page_id, '_wpml_trid', $lang_details->trid );
					$active_languages = $this->get_active_languages();
					$res              = $this->get_element_translations( $lang_details->trid );
					$translations     = array();
					foreach ( $active_languages as $k => $v ) {
						if ( $page_id != $res[ $k ]->element_id ) {
							$translations[ $k ] = isset( $res[ $k ]->element_id ) ? $res[ $k ]->element_id : 0;
						}
					}
					update_post_meta( $page_id, '_wpml_translations', wp_json_encode( $translations ) );
				}
				break;
			case 'metaWeblog.getPost':
				if ( isset( $params['methodCall']['params']['param'][0]['value']['int']['value'] ) ) {
					$page_id      = (int) filter_var( $params['methodCall']['params']['param'][0]['value']['int']['value'], FILTER_SANITIZE_NUMBER_INT );
					$lang_details = $this->get_element_language_details( $page_id, 'post_' . get_post_type( $page_id ) );
					$this->set_this_lang( $lang_details->language_code );
					update_post_meta( $page_id, '_wpml_language', $lang_details->language_code );
					update_post_meta( $page_id, '_wpml_trid', $lang_details->trid );
					$active_languages = $this->get_active_languages();
					$res              = $this->get_element_translations( $lang_details->trid );
					$translations     = array();
					foreach ( $active_languages as $k => $v ) {
						if ( isset( $res[ $k ] ) && $page_id != $res[ $k ]->element_id ) {
							$translations[ $k ] = isset( $res[ $k ]->element_id ) ? $res[ $k ]->element_id : 0;
						}
					}
					update_post_meta( $page_id, '_wpml_translations', wp_json_encode( $translations ) );
				}
				break;
			case 'metaWeblog.getRecentPosts':
				if ( isset( $params['methodCall']['params']['param'][3]['value']['int']['value'] ) ) {
					$num_posts = (int) filter_var( $params['methodCall']['params']['param'][3]['value']['int']['value'], FILTER_SANITIZE_NUMBER_INT );
					if ( $num_posts ) {
						$posts = get_posts(
                            [
								'suppress_filters' => false,
								'numberposts'      => $num_posts,
							]
                        );
						foreach ( $posts as $p ) {
							$lang_details = $this->get_element_language_details( $p->ID, 'post_post' );
							update_post_meta( $p->ID, '_wpml_language', $lang_details->language_code );
							update_post_meta( $p->ID, '_wpml_trid', $lang_details->trid );
							$active_languages = $this->get_active_languages();
							$res              = $this->get_element_translations( $lang_details->trid );
							$translations     = array();
							foreach ( $active_languages as $k => $v ) {
								if ( $p->ID != $res[ $k ]->element_id ) {
									$translations[ $k ] = isset( $res[ $k ]->element_id ) ? $res[ $k ]->element_id : 0;
								}
							}
							update_post_meta( $p->ID, '_wpml_translations', wp_json_encode( $translations ) );
						}
					}
				}
				break;
		}
	}

	function xmlrpc_get_languages_list( $lang ) {
		$wpdb = $this->wpdb;

		if ( ! is_null( $lang ) ) {
			if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT code FROM {$wpdb->prefix}icl_languages WHERE code=%s", $lang ) ) ) {
				$IXR_Error = new IXR_Error( 401, __( 'Invalid language code', 'sitepress' ) );
				echo $IXR_Error->getXml();
				exit( 1 );
			}
			$this->admin_language = $lang;
		}
		define( 'WP_ADMIN', true );
		$active_languages = $this->get_active_languages( true );

		return $active_languages;
	}

	function xml_unprotect_wpml_meta( $protected, $meta_key, $meta_type ) {
		$metas_list = array( '_wpml_trid', '_wpml_translations', '_wpml_language' );
		if ( in_array( $meta_key, $metas_list, true ) ) {
			$protected = false;
		}

		return $protected;
	}

	function meta_generator_tag() {
		$lids             = array();
		$active_languages = $this->get_active_languages();
		if ( $active_languages ) {
			foreach ( $active_languages as $l ) {
				$lids[] = $l['id'];
			}
			$stt  = join( ',', $lids );
			$stt .= ';';
			printf( '<meta name="generator" content="WPML ver:%s stt:%s" />' . PHP_EOL, ICL_SITEPRESS_VERSION, $stt );
		}
	}

	function get_language_cookie() {
		global $wpml_request_handler;

		return $wpml_request_handler->get_cookie_lang();
	}

	function set_admin_language_cookie( $lang = false ) {
		if ( is_admin() ) {
			global $wpml_request_handler;

			$wpml_request_handler->set_language_cookie( $lang ? $lang : $this->get_default_language() );
		}
	}

	function get_admin_language_cookie() {
		global $wpml_request_handler;

		return ( is_admin() || wpml_is_rest_request() ) ? $wpml_request_handler->get_cookie_lang() : null;
	}

	function reset_admin_language_cookie() {
		$this->set_admin_language_cookie( $this->get_default_language() );
	}

	function rewrite_rules_filter( $value ) {
		global $wpml_language_resolution, $wpml_url_converter;

		$active_language_codes = $wpml_language_resolution->get_active_language_codes();
		$language_codes_map    = (array) array_combine( $active_language_codes, $active_language_codes );
		$language_codes_map    = apply_filters( 'wpml_language_codes_map', $language_codes_map );
		$active_language_codes = array_map(
			static function ( $language_code ) use ( $language_codes_map ) {
				return $language_codes_map[ $language_code ] ?? $language_code;
			},
			$active_language_codes
		);

		$filter = new WPML_Rewrite_Rules_Filter(
			$active_language_codes,
			home_url(),
			$wpml_url_converter ? $wpml_url_converter->get_abs_home() : get_option( 'home' )
		);

		return $filter->restore_home_root( $value );
	}

	function is_rtl( $lang = false ) {
		if ( is_admin() ) {
			if ( empty( $lang ) ) {
				$lang = $this->get_admin_language();
			}
		} elseif ( empty( $lang ) ) {
				$lang = $this->get_current_language();
		}

		return \WPML\LanguageEditor\RtlLanguages::isRtl( (string) $lang );
	}

	function get_translatable_documents_filter( $default = array() ) {
		$post_types = $this->get_translatable_documents( false );
		if ( ! $post_types ) {
			$post_types = $default;
		}

		return $post_types;
	}

	function get_translatable_documents( $include_not_synced = false ) {
		$translatable_post_types = array();

		$exceptions = array( 'revision', 'nav_menu_item' );

		$translation_modes = new WPML_Translation_Modes();

		foreach (
			$this->get_wp_api()
				->get_wp_post_types_global() as $k => $v
		) {
			if ( ! in_array( $k, $exceptions ) ) {
				if ( ! $include_not_synced &&
					(
						empty( $this->settings['custom_posts_sync_option'][ $k ] ) ||
						! $translation_modes->is_translatable_mode( $this->settings['custom_posts_sync_option'][ $k ] )
					)
				) {
					continue;
				}
				$translatable_post_types[ $k ] = $v;
			}
		}

		$translatable_post_types = apply_filters( 'get_translatable_documents', $translatable_post_types );

		$readonly_config = wpml_get_tm_sub_setting( 'custom-types_readonly_config', null );
		if ( null !== $readonly_config ) {
			$cpt_unlocked_options    = $this->get_setting( 'custom_posts_unlocked_option', array() );
			$settings_filters        = new WPML_Settings_Filters();
			$translatable_post_types = $settings_filters->get_translatable_documents( $translatable_post_types, $readonly_config, $cpt_unlocked_options );
		}

		return apply_filters( 'get_translatable_documents_all', $translatable_post_types );
	}

	public function get_display_as_translated_documents() {
		$display_as_translated_post_types = array();

		foreach (
			$this->get_wp_api()
				->get_wp_post_types_global() as $k => $v
		) {
			if ( isset( $this->settings['custom_posts_sync_option'][ $k ] ) &&
				WPML_CONTENT_TYPE_DISPLAY_AS_IF_TRANSLATED == $this->settings['custom_posts_sync_option'][ $k ]
			) {
				$display_as_translated_post_types[ $k ] = $v;
			}
		}

		return apply_filters( 'get_display_as_translated_documents', $display_as_translated_post_types );
	}

	function get_translatable_taxonomies( $include_not_synced = false, $deprecated = 'post' ) {
		global $wp_taxonomies;
		$t_taxonomies = array();
		foreach ( (array) $wp_taxonomies as $taxonomy_name => $taxonomy ) {
			if ( 'post_format' === $taxonomy_name ) {
				continue;
			}
			if ( ! empty( $this->settings['taxonomies_sync_option'][ $taxonomy_name ] ) ) {
				$t_taxonomies[] = (string) $taxonomy_name;
			}
		}

		if ( has_filter( 'get_translatable_taxonomies' ) ) {
			$filtered     = apply_filters(
				'get_translatable_taxonomies',
				array(
					'taxs'        => $t_taxonomies,
					'object_type' => $deprecated,
				)
			);
			$t_taxonomies = $filtered['taxs'];
			if ( empty( $t_taxonomies ) ) {
				$t_taxonomies = array();
			}
		}

		return $t_taxonomies;
	}

	function is_translated_taxonomy( $tax ) {
		$option_key          = 'taxonomies_sync_option';
		$readonly_config_key = 'taxonomies_readonly_config';

		$translated = apply_filters( 'pre_wpml_is_translated_taxonomy', null, $tax );

		return $translated !== null
			? $translated
			: $this->is_translated_element( $tax, $option_key, $readonly_config_key, WPML_Settings_Helper::KEY_TAXONOMY_UNLOCK_OPTION );
	}

	public function is_display_as_translated_taxonomy( $tax ) {
		return isset( $this->settings['taxonomies_sync_option'][ $tax ] ) &&
				WPML_CONTENT_TYPE_DISPLAY_AS_IF_TRANSLATED == $this->settings['taxonomies_sync_option'][ $tax ];
	}

	public function get_display_as_translated_taxonomies() {
		global $wp_taxonomies;

		$taxonomies = array();

		foreach ( (array) $wp_taxonomies as $taxonomy_name => $taxonomy ) {
			if ( $this->is_display_as_translated_taxonomy( $taxonomy_name ) ) {
				$taxonomies[] = (string) $taxonomy_name;
			}
		}

		return apply_filters( 'get_display_as_translated_taxonomies', $taxonomies );
	}


	public function is_translated_post_type_filter( $value, $post_type ) {
		return $this->is_translated_post_type( $post_type );
	}

	public function is_translated_post_type( $type ) {

		$translated = apply_filters( 'pre_wpml_is_translated_post_type', null, $type );

		return $translated !== null
			? $translated
			: $this->is_translated_element( $type, 'custom_posts_sync_option', 'custom-types_readonly_config', WPML_Settings_Helper::KEY_CPT_UNLOCK_OPTION );
	}

	public function is_display_as_translated_post_type_filter( $value, $post_type ) {
		return $this->is_display_as_translated_post_type( $post_type );
	}

	public function is_display_as_translated_post_type( $type ) {
		return isset( $this->settings['custom_posts_sync_option'][ $type ] ) &&
				WPML_CONTENT_TYPE_DISPLAY_AS_IF_TRANSLATED == $this->settings['custom_posts_sync_option'][ $type ];
	}

	public function is_translated_taxonomy_filter( $value, $taxonomy ) {
		return $this->is_translated_taxonomy( $taxonomy );
	}

	function verify_post_translations_action( $post_types ) {
		if ( ! is_array( $post_types ) ) {
			$post_types = (array) $post_types;
		}
		foreach ( $post_types as $post_type => $translate ) {
			if ( $translate && ! is_numeric( $post_type ) ) {
				$this->verify_post_translations( $post_type );
			}
		}
	}

	public function verify_post_translations( $post_type ) {
		$set_default_language = new WPML_Initialize_Language_For_Post_Type( $this->wpdb );
		$set_default_language->run( $post_type, $this->get_default_language() );
	}

	function verify_taxonomy_translations( $taxonomy ) {
		$term_utils = new WPML_Terms_Translations();
		$tax_sync   = new WPML_Term_Language_Synchronization(
			$this,
			$term_utils,
			$taxonomy
		);
		if ( $this->get_setting( 'setup_complete' ) ) {
			$tax_sync->set_translated();
		} else {
			$tax_sync->set_initial_term_language();
		}
		delete_option( $taxonomy . '_children' );
	}

	function wp_upgrade_locale( $locale ) {
		$default_language = $this->get_default_language();
		$default_locale   = $this->get_locale_from_language_code( $default_language );

		return defined( 'WPLANG' ) && WPLANG ? WPLANG : $default_locale;
	}

	function admin_language_switcher() {
		require_once WPML_PLUGIN_PATH . '/menu/wpml-admin-lang-switcher.class.php';
		$admin_lang_switcher = new WPML_Admin_Language_Switcher();
		$admin_lang_switcher->render();
	}

	function admin_notices( $message, $class = 'updated' ) {
		static $hook_added      = 0;
		$this->_admin_notices[] = array(
			'class'   => $class,
			'message' => $message,
		);

		if ( ! $hook_added ) {
			add_action( 'admin_notices', array( $this, '_admin_notices_hook' ) );
		}

		$hook_added = 1;
	}

	function _admin_notices_hook() {
		if ( ! empty( $this->_admin_notices ) ) {
			foreach ( $this->_admin_notices as $n ) {
				echo '<div class="' . $n['class'] . '">';
				echo '<p>' . $n['message'] . '</p>';
				echo '</div>';
			}
		}
	}

	function allowed_redirect_hosts( $hosts ) {
		if ( WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN === WPML_URL_Format::of( $this->settings ) ) {
			$allowed_redirect_hosts = new WPML_Allowed_Redirect_Hosts( $this );
			$hosts                  = $allowed_redirect_hosts->get_hosts( $hosts );
		}

		return $hosts;
	}

	public static function get_installed_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$wp_plugins        = get_plugins();
		$wpml_plugins_list = array(
			'WPML Multilingual CMS'      => array(
				'installed' => false,
				'active'    => false,
				'file'      => false,
				'plugin'    => false,
				'slug'      => 'sitepress-multilingual-cms',
			),
			'WPML CMS Navigation'        => array(
				'installed' => false,
				'active'    => false,
				'file'      => false,
				'plugin'    => false,
				'slug'      => 'wpml-cms-nav',
			),
			'WPML String Translation'    => array(
				'installed' => false,
				'active'    => false,
				'file'      => false,
				'plugin'    => false,
				'slug'      => 'wpml-string-translation',
			),
			'WPML Sticky Links'          => array(
				'installed' => false,
				'active'    => false,
				'file'      => false,
				'plugin'    => false,
				'slug'      => 'wpml-sticky-links',
			),
			'WPML Media Translation'     => array(
				'installed' => false,
				'active'    => false,
				'file'      => false,
				'plugin'    => false,
				'slug'      => 'wpml-media-translation',
			),
			'WPML Troubleshooting'       => array(
				'installed' => false,
				'active'    => false,
				'file'      => false,
				'plugin'    => false,
				'slug'      => 'wpml-troubleshooting',
			),
			'WPML Multilingual & Multicurrency for WooCommerce' => array(
				'installed' => false,
				'active'    => false,
				'file'      => false,
				'plugin'    => false,
				'slug'      => 'woocommerce-multilingual',
			),
			'Gravity Forms Multilingual' => array(
				'installed' => false,
				'active'    => false,
				'file'      => false,
				'plugin'    => false,
				'slug'      => 'gravityforms-multilingual',
			),
			'WPML SEO'                   => array(
				'installed' => false,
				'active'    => false,
				'file'      => false,
				'plugin'    => false,
				'slug'      => 'wp-seo-multilingual',
			),
		);

		foreach ( $wpml_plugins_list as $wpml_plugin_name => $v ) {
			foreach ( $wp_plugins as $file => $plugin ) {
				$plugin_name = $plugin['Name'];
				if ( $plugin_name == $wpml_plugin_name ) {
					$wpml_plugins_list[ $plugin_name ]['installed'] = true;
					$wpml_plugins_list[ $plugin_name ]['plugin']    = $plugin;
					$wpml_plugins_list[ $plugin_name ]['file']      = $file;
				}
			}
		}

		return $wpml_plugins_list;
	}

	public function get_backtrace( $limit = WPML\Utils\DebugBackTrace::DEFAULT_LIMIT, $provide_object = false, $ignore_args = true ) {
		$options = false;

		if ( version_compare( $this->wp_api->phpversion(), '5.3.6' ) < 0 ) {
			$options = $provide_object;
		} else {
			if ( $provide_object ) {
				$options |= DEBUG_BACKTRACE_PROVIDE_OBJECT;
			}
			if ( $ignore_args ) {
				$options |= DEBUG_BACKTRACE_IGNORE_ARGS;
			}
		}
		if ( version_compare( $this->wp_api->phpversion(), '5.4.0' ) >= 0 ) {
			$actual_limit    = $limit == 0 ? 0 : $limit + 1;
			$debug_backtrace = debug_backtrace( $options, $actual_limit );
		} elseif ( version_compare( $this->wp_api->phpversion(), '5.2.4' ) >= 0 ) {
			$debug_backtrace = debug_backtrace();
		} else {
			$debug_backtrace = debug_backtrace( $options );
		}

		if ( $debug_backtrace ) {
			array_shift( $debug_backtrace );
		}

		return $debug_backtrace;
	}

	function url_to_postid( $url ) {

		if (
			Url::isLogin( $url )
			|| Url::isAdmin( $url )
			|| Url::isContentDirectory( $url )
		) {
			return $url;
		}

		$is_language_in_domain = false;
		$is_translated_domain  = false;
		$has_switched_lang     = false;

		if ( WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN === WPML_URL_Format::of( $this->settings ) && isset( $this->settings['language_domains'] ) ) {
			$is_language_in_domain = true;
			$domains = array_filter( $this->get_setting( 'language_domains' ) );
			foreach ( $domains as $code => $domain ) {
				if ( strpos( $url, (string) $domain ) === 0 ) {
					$is_translated_domain = true;
					$has_switched_lang    = true;
					$this->switch_lang( $code );
					$url = str_replace( $domain, site_url(), $url );
					break;
				}
			}

			if ( ! $is_translated_domain ) {
				$has_switched_lang = true;
				$default_language  = $this->get_default_language();
				$this->switch_lang( $default_language );
			}
		}

		try {
			global $absolute_links_object;
			if ( ! isset( $absolute_links_object ) || ! is_a( $absolute_links_object, 'AbsoluteLinks' ) || $is_language_in_domain ) {
				$absolute_links_object = new AbsoluteLinks();
			}

			$original_url = $url;
			$site_url = site_url();

			$url = WPML_Same_Site_Url_Normalizer::normalize_url( $url );

			$html             = '<a href="' . $url . '">removeit</a>';
			$alp_broken_links = array();
			remove_filter( 'url_to_postid', array( $this, 'url_to_postid' ) );
			$html = $absolute_links_object->_process_generic_text( $html, $alp_broken_links );
			add_filter( 'url_to_postid', array( $this, 'url_to_postid' ) );
			$url = str_replace( array( '<a href="', '">removeit</a>' ), array( '', '' ), $html );
		} finally {
			if ( $has_switched_lang ) {
				$this->switch_lang();
			}
		}

		if ( 0 === strpos( $original_url, (string) $site_url ) ) {

			$url2 = $this->cpt_url_to_id_url( $url, $original_url );

			if ( $url2 == $url && $original_url != $url ) {
				$url = $this->maybe_adjust_url( $url, $original_url );
			} else {
				$url = $url2;
			}
		}

		return $url;
	}

	function cpt_url_to_id_url( $url, $original_url ) {

		$parsed_url = wpml_parse_url( $url );

		if ( ! isset( $parsed_url['query'] ) ) {
			return $url;
		}

		$query = $parsed_url['query'];

		parse_str( $query, $query_vars );

		$args = array(
			'public'   => true,
			'_builtin' => false,
		);

		$post_types = get_post_types( $args, 'objects' );

		foreach ( $post_types as $name => $post_type_object ) {
			$slug = trim( Obj::pathOr( '', [ 'rewrite', 'slug' ], $post_type_object ), '/' );
			if ( $slug && isset( $query_vars[ $slug ] ) ) {
				$post_type = $name;
				$post_slug = $query_vars[ $slug ];
				break;
			}
		}

		if ( ! isset( $post_type, $post_slug ) ) {
			return $url;
		}

		$args = array(
			'name'      => $post_slug,
			'post_type' => $post_type,
		);

		$post = new WP_Query( $args );

		if ( ! isset( $post->post ) ) {
			return $url;
		}

		$id = $post->post->ID;

		$post_language = $this->get_language_for_element( $id, 'post_' . $post_type );

		$url_language = $this->get_language_of_url( $original_url );

		$new_vars = array();
		if ( $post_language != $url_language ) {

			$trid         = $this->get_element_trid( $id, 'post_' . $post_type );
			$translations = $this->get_element_translations( $trid, 'post_' . $post_type );

			if ( isset( $translations[ $url_language ] ) ) {
				$translation = $translations[ $url_language ];
				if ( isset( $translation->element_id ) ) {
					$new_vars['p'] = $translation->element_id;
				}
			}
		} else {
			$new_vars['p'] = $id;
		}

		$new_query = http_build_query( $new_vars );

		$url = str_replace( $query, $new_query, $url );

		return $url;
	}

	private function maybe_adjust_url( $url, $original_url ) {
		$parsed_url = wpml_parse_url( $url );
		$query      = isset( $parsed_url['query'] ) ? $parsed_url['query'] : '';

		parse_str( $query, $query_vars );

		$post_id           = null;
		$post_id_query_var = null;
		if ( isset( $query_vars['page_id'] ) ) {
			$post_id_query_var = 'page_id';
		} elseif ( isset( $query_vars['p'] ) ) {
			$post_id_query_var = 'p';
		}

		if ( $post_id_query_var ) {
			$post_id = $query_vars[ $post_id_query_var ];
		}

		if ( $post_id ) {
			$post_id       = (int) $post_id;
			$post_type     = get_post_type( $post_id );
			$post_language = $this->get_language_for_element( $post_id, 'post_' . $post_type );
			$url_language  = $this->get_language_of_url( $original_url );
			if ( $post_language !== $url_language ) {
				$trid         = $this->get_element_trid( $post_id, 'post_' . $post_type );
				$translations = $this->get_element_translations( $trid, 'post_' . $post_type );
				if ( isset( $translations[ $url_language ] ) ) {
					$translation = $translations[ $url_language ];
					if ( isset( $translation->element_id ) ) {
						$query_vars[ $post_id_query_var ] = $translation->element_id;
						$new_query                        = http_build_query( $query_vars );
						$url                              = str_replace( $query, $new_query, $url );
					}
				}
			}
		}

		return $url;
	}

	function get_language_from_url( $url ) {
		global $wpml_url_converter;

		return $wpml_url_converter->get_language_from_url( $url );
	}

	private function get_language_of_url( $url ) {
		global $wpml_url_converter;

		return $wpml_url_converter->get_language_of_url( $url );
	}

	function update_index_screen() {
		return include WPML_PLUGIN_PATH . '/menu/theme-plugins-compatibility.php';
	}

	function get_search_form_filter( $form ) {
		$language_form_field = wpml_get_language_form_field();
		if ( strpos( $form, (string) $language_form_field ) === false
			&& WPML_LANGUAGE_NEGOTIATION_TYPE_PARAMETER === (int) $this->get_setting( 'language_negotiation_type' )
		) {
			$form = str_replace( '</form>', $language_form_field . '</form>', $form );
		}

		return $form;
	}

	public function get_string_translation_settings( $key = '' ) {
		$setting = $this->get_setting( 'st' );

		if ( $this->setting_array_is_set_or_has_key( $setting, $key ) ) {
			$setting = $setting[ $key ];
		}

		return $setting;
	}

	private function setting_array_is_set_or_has_key( $setting, $key ) {
		return $key != '' && $setting && isset( $setting[ $key ] );
	}

	private function is_translated_element( $element_type, $option_key, $readonly_config_key, $unlocked_key ) {
		$ret = false;

		if ( is_scalar( $element_type ) ) {
			$readonly_config = wpml_get_tm_sub_setting( $readonly_config_key, false );
			if ( 'any' === $element_type ) {
				$ret = $readonly_config || count( (array) $this->get_setting( $option_key ) ) > 0;
			} else {
				$ret = icl_get_sub_setting( $option_key, $element_type );
				if ( ! $ret ) {
					$is_read_only_translatable = isset( $readonly_config[ $element_type ] )
												&& 1 === (int) $readonly_config[ $element_type ];

					$unlocked            = $this->get_setting( $unlocked_key, array() );
					$is_setting_unlocked = isset( $unlocked[ $element_type ] ) && $unlocked[ $element_type ];

					if ( $is_read_only_translatable && ! $is_setting_unlocked ) {
						$ret = true;
					} else {
						$ret = false;
					}
				}
			}
		}

		return (bool) $ret;
	}

	public function get_always_translatable_post_types() {
		return array();
	}

	function get_duplicates( $master_post_id ) {
		$this->post_duplication = $this->post_duplication === null ? new WPML_Post_Duplication( $this->wpdb, $this )
			: $this->post_duplication;

		return $this->post_duplication->get_duplicates( $master_post_id );
	}

	function make_duplicate( $master_post_id, $lang, $overwrite_translation = false ) {
		$this->post_duplication = $this->post_duplication === null ? new WPML_Post_Duplication( $this->wpdb, $this )
			: $this->post_duplication;

		return $this->post_duplication->make_duplicate( $master_post_id, $lang, $overwrite_translation );
	}

	function get_new_post_source_id( $post_id ) {
		global $pagenow;

		if ( $pagenow == 'post-new.php' && isset( $_GET['trid'] ) && isset( $_GET['source_lang'] ) ) {
			$translations = $this->get_element_translations( $_GET['trid'] );

			if ( isset( $translations[ $_GET['source_lang'] ] ) ) {
				$post_id = $translations[ $_GET['source_lang'] ]->element_id;
			}
		}

		return $post_id;
	}

	function get_object_id( $element_id, $element_type = 'post', $return_original_if_missing = false, $language_code = null ) {
		global $wp_post_types, $wp_taxonomies;
		global $wpml_post_translations;
		global $wpml_term_translations;

		$ret_element_id = null;

		if ( $element_id ) {
			$language_code = $language_code ?: $this->get_current_language();

			$element_type = $element_type === 'any' ? get_post_type( $element_id ) : $element_type;

			if ( $element_type ) {
				$postTypeIsTranslatable     = is_post_type_translated( $element_type );
				$taxonomyTypeIsTranslatable = is_taxonomy_translated( $element_type );
				if ( $postTypeIsTranslatable || $taxonomyTypeIsTranslatable ) {
					$post_id = isset( $wp_post_types[ $element_type ] ) && $postTypeIsTranslatable
						? $wpml_post_translations->element_id_in( $element_id, $language_code )
						: null;

					$term_id = ! $post_id && isset( $wp_taxonomies[ $element_type ] ) && $taxonomyTypeIsTranslatable
						? $wpml_term_translations->term_id_in( $element_id, $language_code )
						: null;

					$ret_element_id = $post_id ?: $term_id ?: ( $return_original_if_missing ? $element_id : null );
				} else {
					$ret_element_id = $element_id;
				}
			}
		}

		return $ret_element_id ? (int) $ret_element_id : null;
	}

	public function handle_head_hreflang() {
		( new WPML_SEO_HeadLangs( $this ) )->init_hooks();
	}

	public function get_current_request_data( $key, $default = null ) {
		return isset( $this->current_request_data[ $key ] ) ? $this->current_request_data[ $key ] : $default;
	}

	public function set_current_request_data( $key, $data ) {
		$this->current_request_data[ $key ] = $data;
	}

	public function clear_current_request_data( $key ) {
		unset( $this->current_request_data[ $key ] );
	}

	public function load_core_tm() {
		$iclTranslationManagement = wpml_load_core_tm();
	}

	public function is_setup_complete() {
		return $this->get_setting( 'setup_complete' );
	}

	private function is_taxonomy_related_page() {
		return isset( $_GET['page'] )
				&& ( $_GET['page'] == WPML_PLUGIN_FOLDER . '/menu/taxonomy-translation.php'
					|| $_GET['page'] == WPML_PLUGIN_FOLDER . '/menu/menu-sync/menus-sync.php'
					|| $_GET['page'] == WPML_PLUGIN_FOLDER . '/menu/term-taxonomy-menus/taxonomy-translation-display.class.php' );
	}

	private function is_saving_taxonomy_labels() {
		global $pagenow;

		return ( $pagenow === 'admin-ajax.php'
				&& isset( $_POST['action'] )
				&& $_POST['action'] === 'wpml_tt_save_labels_translation' );
	}

	private function switch_to_admin_language() {
		$this->switch_lang( $this->get_admin_language(), true );
	}

	private function move_current_language_to_the_top() {
		$active_languages = $this->get_active_languages();
		foreach ( $active_languages as $k => $active_lang ) {
			if ( $k === $this->this_lang ) {
				unset( $this->active_languages[ $k ] );
				$this->active_languages = array_merge( array( $k => $active_lang ), $this->active_languages );
				break;
			}
		}
	}

	private function maybeHideLanguages( array $active_languages ) {
		$mustHideLanguages = isset( $this->wp_query->query_vars['post_type'] ) &&
							! is_array( $this->wp_query->query_vars['post_type'] ) &&
							! empty( $this->wp_query->query_vars['post_type'] )
							&& ! $this->is_translated_post_type( $this->wp_query->query_vars['post_type'] );

		if ( $mustHideLanguages ) {
			foreach ( $active_languages as $lang => $element ) {
				unset( $active_languages[ $lang ] );
			}
		}

		return $active_languages;
	}

	private function is_page_query() {
		return ( ! empty( $this->wp_query->queried_object_id ) && ( isset( $this->wp_query->query['paged'] ) || isset( $this->wp_query->query['page'] ) ) && $this->wp_query->queried_object_id == get_option( 'page_for_posts' ) );
	}

	public function get_domain_by_language( $language ) {
		$default_domain = $this->get_default_domain();

		if ( $this->is_language_domain_setting_disabled() || $language == $this->get_default_language() ) {
			return $default_domain;
		}

		$language_domains = $this->get_setting( 'language_domains', array() );
		$language_domains = is_array( $language_domains ) ? $language_domains : array();

		if ( ! isset( $language_domains[ $language ] ) ) {
			return $default_domain;
        }

		return WPML_Language_Domains::baseUrlOf(
			(string) $language_domains[ $language ],
			(string) wpml_parse_url( $default_domain, PHP_URL_SCHEME )
		);
	}


	public function is_language_domain_setting_enabled() {
		return $this->get_setting( 'language_negotiation_type' ) == WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN;
	}

	public function is_language_domain_setting_disabled() {
		return ! $this->is_language_domain_setting_enabled();
	}

	public function get_default_domain() {
		$default_language = $this->get_default_language();
		return rtrim( (string) $this->convert_url( $this->get_wp_api()->get_home_url(), $default_language ), '/' );
	}

	public function has_uploaded_media() {
		$wpdb = $this->wpdb;

		$count = (int) $this->wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'"
		);
		return $count > 0;
	}

	public function executeSavePostHookOnPostTranslationSave( $post_id ) {
		if ( ! is_object( $this->wpml_save_post_hooks ) ) {
			return;
		}

		$this->wpml_save_post_hooks->executeOnPostTranslationSave( $post_id );
	}
}
