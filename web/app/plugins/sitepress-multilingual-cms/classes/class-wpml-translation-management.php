<?php

use function WPML\Container\make;
use WPML\Core\Component\CustomFieldPreferences\Domain\ElementType;
use WPML\LIB\WP\User;
use WPML\TM\Settings\PreferenceWriter;
use WPML\UserInterface\Web\Core\Component\Notices\AutomaticServiceSelection\Application\AutomaticServiceSelectionNoticeController;

class WPML_Translation_Management {

	const PAGE_SLUG_MANAGEMENT = '/menu/main.php';
	const PAGE_SLUG_SETTINGS   = '/menu/settings';
	const PAGE_SLUG_QUEUE      = '/menu/translations-queue.php';

	var $load_priority = 200;

	protected $sitepress;

	private $tm_loader;

	private $tm_instance;

	private $tm_queue;

	private $wpml_tm_menus_management;

	private $ajax_route;

	private $wpml_tp_translator;

	function __construct( $sitepress, $tm_loader, $tm_instance, ?WPML_TP_Translator $wpml_tp_translator = null ) {
		$this->sitepress = $sitepress;

		$this->tm_loader   = $tm_loader;
		$this->tm_instance = $tm_instance;

		$this->wpml_tp_translator = $wpml_tp_translator;
	}

	public function init() {
		global $wpdb;

		$this->disableAllNonWPMLNotices();

		$this->wpml_tm_menus_management = new WPML_TM_Menus_Management();
		\WPML\Container\share( [ WPML_TM_Menus_Management::class => $this->wpml_tm_menus_management ] );
		$mcs_factory = new WPML_TM_Scripts_Factory();
		$mcs_factory->init_hooks();

		if ( null === $this->wpml_tp_translator ) {
			$this->wpml_tp_translator = new WPML_TP_Translator();
		}
		$this->ajax_route = new WPML_Ajax_Route( new WPML_TM_Ajax_Factory( $wpdb, $this->sitepress, $_POST ) );

	}

	public function load() {
		global $pagenow;

		$this->tm_loader->tm_after_load();
		$wpml_wp_api = $this->sitepress->get_wp_api();
		if ( $wpml_wp_api->is_admin() ) {
			$this->tm_loader->load_xliff_frontend();
		}
		if ( $this->notices_added_because_wpml_is_inactive_or_incomplete() ) {
			return false;
		}

		$this->handle_get_requests();

		$this->tm_loader->load_pro_translation( $wpml_wp_api );
		if ( $wpml_wp_api->is_admin() ) {
			$this->add_pre_tm_init_admin_hooks();
			do_action( 'wpml_tm_init' );
			$this->add_post_tm_init_admin_hooks( $pagenow );
		}

		$this->api_hooks();

		add_filter( 'wpml_config_white_list_pages', array( $this, 'filter_wpml_config_white_list_pages' ) );
		do_action( 'wpml_tm_loaded' );

		return true;
	}

	public function api_hooks() {
		add_action( 'wpml_save_custom_field_translation_option', array( $this, 'wpml_save_custom_field_translation_option' ), 10, 2 );
	}

	private function notices_added_because_wpml_is_inactive_or_incomplete() {
		$wpml_wp_api = $this->sitepress->get_wp_api();
		if ( ! $wpml_wp_api->constant( 'ICL_SITEPRESS_VERSION' ) || $wpml_wp_api->constant( 'ICL_PLUGIN_INACTIVE' ) ) {
			return true;
		} elseif ( ! $this->sitepress->get_setting( 'setup_complete' ) ) {
			$this->maybe_show_wpml_not_installed_warning();

			return true;
		}

		return false;
	}

	public function filter_wpml_config_white_list_pages( array $white_list_pages ) {
		$white_list_pages[] = WPML_TM_FOLDER . self::PAGE_SLUG_MANAGEMENT;
		$white_list_pages[] = WPML_TM_FOLDER . self::PAGE_SLUG_SETTINGS;

		return $white_list_pages;
	}

	public function maybe_show_wpml_not_installed_warning() {
		if ( ! ( isset( $_GET['page'] ) && 'sitepress-multilingual-cms/menu/languages.php' === $_GET['page'] ) ) {
			add_action( 'admin_notices', array( $this, '_wpml_not_installed_warning' ) );
		}
	}

	function is_jobs_tab() {
		return $this->is_tm_page( 'jobs' );
	}

	function is_translators_tab() {
		return WPML_TM_Subview_Detection::is_on_main_or_settings_page()
			   && 'translators' === WPML_TM_Subview_Detection::current_subview();
	}

	function admin_enqueue_scripts() {
		if ( ! defined( 'DOING_AJAX' ) ) {

			wp_register_script(
				'wpml-tm-progressbar',
				WPML_TM_URL . '/res/js/wpml-progressbar.js',
				array(
					'jquery',
					'jquery-ui-progressbar',
					'backbone',
				),
				ICL_SITEPRESS_SCRIPT_VERSION
			);
			wp_register_script(
				'wpml-tm-scripts',
				WPML_TM_URL . '/res/js/scripts-tm.js',
				array(
					'jquery',
					'sitepress-scripts',
				),
				ICL_SITEPRESS_SCRIPT_VERSION
			);
			wp_enqueue_script( 'wpml-tm-scripts' );

			wp_enqueue_style( 'wpml-tm-styles', WPML_TM_URL . '/res/css/style.css', array(), ICL_SITEPRESS_SCRIPT_VERSION );

			if ( filter_input( INPUT_GET, 'page' ) === WPML_TM_FOLDER . '/menu/main.php' ) {
				if ( isset( $_GET['sm'] ) && $_GET['sm'] === 'translators' ) {

					wp_enqueue_script( 'wpml-select-2', ICL_PLUGIN_URL . '/lib/select2/select2.min.js', array( 'jquery' ), ICL_SITEPRESS_SCRIPT_VERSION, true );

					wp_enqueue_script(
						'wpml-tm-translation-roles-select2',
						WPML_TM_URL . '/res/js/translation-roles-select2.js',
						array(),
						ICL_SITEPRESS_SCRIPT_VERSION
					);

					wp_enqueue_script(
						'wpml-tm-set-translation-roles',
						WPML_TM_URL . '/res/js/set-translation-role.js',
						array( 'underscore' ),
						ICL_SITEPRESS_SCRIPT_VERSION
					);
				}

				wp_enqueue_script(
					'wpml-tm-translation-proxy',
					WPML_TM_URL . '/res/js/translation-proxy.js',
					array( 'wpml-tm-scripts', 'jquery-ui-dialog' ),
					ICL_SITEPRESS_SCRIPT_VERSION
				);
			}

			if ( WPML_TM_Page::is_settings() ) {
				WPML_Simple_Language_Selector::enqueue_scripts();
			}

			wp_enqueue_style( 'wp-jquery-ui-dialog' );
			wp_enqueue_script( 'thickbox' );
			do_action( 'wpml_tm_scripts_enqueued' );
		}
	}

	function admin_print_styles() {
		if ( ! defined( 'DOING_AJAX' ) ) {
			wp_enqueue_style(
				'wpml-tm-styles',
				WPML_TM_URL . '/res/css/style.css',
				array(),
				ICL_SITEPRESS_SCRIPT_VERSION
			);

			if ( $this->sitepress->get_wp_api()->is_translation_queue_page() ) {
				wp_enqueue_style(
					'wpml-tm-editor-css',
					WPML_TM_URL . '/res/css/translation-editor/translation-editor.css',
					array(),
					ICL_SITEPRESS_SCRIPT_VERSION
				);
				wp_enqueue_style( OTGS_Assets_Handles::POPOVER_TOOLTIP );
				wp_enqueue_script( OTGS_Assets_Handles::POPOVER_TOOLTIP );
			}

			wp_enqueue_style( 'wpml-dialog' );
			wp_enqueue_style( OTGS_Assets_Handles::SWITCHER );
		}
	}

	function translation_service_js_data( $data ) {
		$data['nonce']['translation_service_authentication'] = wp_create_nonce( 'translation_service_authentication' );
		$data['nonce']['translation_service_toggle']         = wp_create_nonce( 'translation_service_toggle' );
		return $data;
	}

	function _wpml_not_installed_warning() {
		?>
		<div class="message error wpml-admin-notice wpml-tm-inactive wpml-not-configured">
			<p>
			<?php
			echo wpml_bold_names(
				__( 'WPML Translation Management is enabled but not effective. Please finish the installation of WPML first.', 'sitepress' )
			);
			?>
			</p></div>
		<?php
	}

	function job_saved_message() {
		?>
			<div class="message updated wpml-admin-notice"><p><?php /* translators: Notice shown after a translation is saved. */ esc_html_e( 'Translation saved.', 'sitepress' ); ?></p></div>
		<?php
	}

	function job_cancelled_message() {
		?>
			<div class="message updated wpml-admin-notice"><p><?php /* translators: Notice shown after a translation is called off. */ esc_html_e( 'Translation cancelled.', 'sitepress' ); ?></p></div>
		<?php
	}


	public function translators_menu( $menu_id ) {
		if ( 'WPML' !== $menu_id ) {
			return;
		}

		$can_manage_translation_management = User::canManageTranslations() || User::hasCap( User::CAP_MANAGE_TRANSLATION_MANAGEMENT );

		$menu               = array();
		$menu['order']      = 400;
		/* translators: Name of the screen where content is sent for translation and translations are managed: in the WPML menu, as the title of that screen, and as the text of links that open it. Plural noun. */
		$menu['page_title'] = __( 'Translations', 'sitepress' );
		/* translators: Name of the screen where content is sent for translation and translations are managed: in the WPML menu, as the title of that screen, and as the text of links that open it. Plural noun. */
		$menu['menu_title'] = __( 'Translations', 'sitepress' );
		$menu['menu_slug']  = WPML_TM_FOLDER . '/menu/translations-queue.php';
		$menu['function']   = array( $this, 'translation_queue_page' );
		$menu['icon_url']   = ICL_PLUGIN_URL . '/res/img/icon16.svg';

		if ( $can_manage_translation_management ) {
			$menu['capability'] = $this->get_required_cap_based_on_current_user_role();
			do_action( 'wpml_admin_menu_register_item', $menu );
		} else {
			$has_language_pairs = (bool) $this->tm_instance->get_current_translator()->language_pairs;
			$menu['capability'] = $has_language_pairs ? User::CAP_TRANSLATE : '';
			$menu               = apply_filters( 'wpml_menu_page', $menu );
			do_action( 'wpml_admin_menu_register_item', $menu );
		}
	}

	function translation_queue_page() {
		if ( true !== apply_filters( 'wpml_tm_lock_ui', false )
			 && $this->is_the_main_request()
		) {
			$this->tm_queue->display();
		}
	}

	public function settings_menu( $menu_id ) {
		if ( 'WPML' !== $menu_id ) {
			return;
		}
		/* translators: Title of the settings screen, and the item in the WPML menu that opens it. */
		$menu_label = __( 'Settings', 'sitepress' );

		$menu               = array();
		$menu['order']      = 9900;
		$menu['page_title'] = $menu_label;
		$menu['menu_title'] = $menu_label;
		$menu['capability'] = $this->get_required_cap_based_on_current_user_role();
		$menu['menu_slug']  = WPML_TM_FOLDER . self::PAGE_SLUG_SETTINGS;
		$menu['function']   = array( $this, 'settings_page' );

		do_action( 'wpml_admin_menu_register_item', $menu );
	}

	public function settings_page() {
		$settings_page = new WPML_TM_Menus_Settings();
		$settings_page->init();
		$settings_page->display_main();
	}

	private function is_the_main_request() {
		return ! isset( $_SERVER['HTTP_ACCEPT'] ) || false !== strpos( $_SERVER['HTTP_ACCEPT'], 'text/html' );
	}

	function plugin_action_links( $links, $file ) {
		$this_plugin = basename( WPML_TM_PATH ) . '/plugin.php';
		if ( $file == $this_plugin ) {
			$links[] = '<a href="admin.php?page=' . basename( WPML_TM_PATH ) . '/menu/main.php">' .
				/* translators: Link next to a plugin that opens its settings. Verb, imperative. */
				__( 'Configure', 'sitepress' ) . '</a>';
		}
		return $links;
	}

	public function automatic_service_selection_action() {
		$this->automatic_service_selection();
	}

	function automatic_service_selection_pages() {
		return $this->is_translators_tab()
			   || (
				   WPML_TM_Subview_Detection::is_on_main_page()
				   && in_array( WPML_TM_Subview_Detection::current_subview(), array( '', 'dashboard' ), true )
			   );
	}

	public function add_com_log_link() {
		WPML_TranslationProxy_Com_Log::add_com_log_link();
	}

	public function service_requires_translators() {
		$result                  = false;
		$service_has_translators = TranslationProxy::translator_selection_available();
		if ( $service_has_translators ) {
			$result = ! $this->service_has_accepted_translators();
		}

		return $result;
	}

	private function service_has_accepted_translators() {
		$result   = false;
		$icl_data = $this->wpml_tp_translator->get_icl_translator_status();
		if ( isset( $icl_data['icl_lang_status'] ) && is_array( $icl_data['icl_lang_status'] ) ) {
			foreach ( $icl_data['icl_lang_status'] as $translator ) {
				if ( isset( $translator['contract_id'] ) && $translator['contract_id'] != 0 ) {
					$result = true;
					break;
				}
			}
		}

		return $result;
	}

	private function is_tm_page( $tab = null ) {
		if ( ! WPML_TM_Subview_Detection::is_on_main_page() ) {
			return false;
		}

		return ! $tab || WPML_TM_Subview_Detection::current_subview() === $tab;
	}

	private function automatic_service_selection() {
		if ( defined( 'DOING_AJAX' ) || ! $this->automatic_service_selection_pages() ) {
			return;
		}
		if ( WPML_Settings_Failsafe_Loader::isUnrecoverable() ) {
			return;
		}

		if ( wp_cache_get( 'done', 'automatic_service_selection' ) ) {
			return;
		}

		$tp_default_suid = TranslationProxy::get_tp_default_suid();
		if ( $tp_default_suid ) {
			$selected_service = TranslationProxy::get_current_service();

			if ( isset( $selected_service->suid ) && $selected_service->suid == $tp_default_suid ) {
				delete_option( AutomaticServiceSelectionNoticeController::OPTION_NAME );

				return;
			}

			try {
				$service_by_suid = TranslationProxy_Service::get_service_by_suid( $tp_default_suid );
			} catch ( Exception $ex ) {
				$service_by_suid = false;
			}
			if ( isset( $service_by_suid->id ) ) {
				$selected_service_id = isset( $selected_service->id ) ? $selected_service->id : false;
				if ( ! $selected_service_id || $selected_service_id != $service_by_suid->id ) {
					if ( WPML_TP_Project_History::ensure_automatic_project_creation_allowed( $service_by_suid ) ) {
						if ( $selected_service_id ) {
							TranslationProxy::deselect_active_service();
						}
						$result = TranslationProxy::select_service( $service_by_suid->id );
						if ( is_wp_error( $result ) ) {
							$error_data_string = $result->get_error_message();
						}
					}
				}
			} else {
				$error_data_string = __( "WPML can't find the translation service. Please contact WPML Support or your translation service provider.", 'sitepress' );
			}
		}
		if ( isset( $error_data_string ) ) {
			update_option(
				AutomaticServiceSelectionNoticeController::OPTION_NAME,
				array( 'message' => $error_data_string ),
				false
			);
		} else {
			delete_option( AutomaticServiceSelectionNoticeController::OPTION_NAME );
		}

		wp_cache_set( 'done', true, 'automatic_service_selection' );
	}

	public function wpml_save_custom_field_translation_option( $custom_field_name, $translation_option ) {
		$custom_field_name = sanitize_text_field( $custom_field_name );
		if ( ! $custom_field_name ) {
			return;
		}

		$available_options  = array(
			WPML_IGNORE_CUSTOM_FIELD,
			WPML_COPY_CUSTOM_FIELD,
			WPML_COPY_ONCE_CUSTOM_FIELD,
			WPML_TRANSLATE_CUSTOM_FIELD,
		);
		$translation_option = absint( $translation_option );
		if ( ! in_array( $translation_option, $available_options ) ) {
			$translation_option = WPML_IGNORE_CUSTOM_FIELD;
		}

		PreferenceWriter::setModes( ElementType::POST, array( $custom_field_name => $translation_option ) );
	}

	private function handle_get_requests() {
		if ( isset( $_GET['wpml_tm_saved'] ) ) {
			add_action( 'admin_notices', array( $this, 'job_saved_message' ) );
		}
		if ( isset( $_GET['wpml_tm_cancel'] ) ) {
			add_action( 'admin_notices', array( $this, 'job_cancelled_message' ) );
		}

		if ( isset( $_GET['icl_action'] ) ) {
			$this->handle_icl_action_reminder_popup();
		}
	}

	private function handle_icl_action_reminder_popup() {
		if ( $_GET['icl_action'] === 'reminder_popup'
			 && isset( $_GET['_icl_nonce'] )
			 && wp_verify_nonce( $_GET['_icl_nonce'], 'reminder_popup_nonce' )
		) {
			add_action( 'init', array( 'TranslationProxy_Popup', 'display' ) );
		} elseif ( $_GET['icl_action'] === 'dismiss_help' ) {
			if ( $this->is_authorized_dismiss_help_request() ) {
				$this->sitepress->set_setting( 'dont_show_help_admin_notice', true, true );
			}
		}
	}

	private function is_authorized_dismiss_help_request() {
		$nonce = isset( $_GET['_icl_nonce'] ) && is_string( $_GET['_icl_nonce'] )
			? sanitize_text_field( wp_unslash( $_GET['_icl_nonce'] ) )
			: '';

		return '' !== $nonce
			&& wp_verify_nonce( $nonce, 'dismiss_help_nonce' )
			&& User::currentUserIsAdmin();
	}

	private function add_pre_tm_init_admin_hooks() {
		add_action( 'init', array( $this, 'automatic_service_selection_action' ) );
		add_action( 'translation_service_authentication', array( $this, 'translation_service_authentication' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml-flush-website-details-cache', \WPML\Request\Policy\Policy::capability( 'manage_translations', \WPML\Request\Policy\Authenticity::actionNonce( 'wpml-flush-website-details-cache', 'nonce' ) ), array( 'TranslationProxy_Translator', 'flush_website_details_cache_action' ) );
		add_action( 'wpml_updated_translation_status', array( 'TranslationProxy_Batch', 'maybe_assign_generic_batch' ), 10, 1 );

		add_filter( 'translation_service_js_data', array( $this, 'translation_service_js_data' ) );
	}

	private function add_translation_in_progress_warning( $pagenow ) {
		if ( in_array( $pagenow, array( 'post-new.php', 'post.php', 'admin-ajax.php' ), true ) ) {
			add_action( 'init', function() {
				$post_edit_notices_factory = new WPML_TM_Post_Edit_Notices_Factory();
				$post_edit_notices_factory->create()->add_hooks();
			} );
		}
	}

	private function add_post_tm_init_admin_hooks( $pagenow ) {
		$this->add_non_theme_customizer_hooks( $pagenow );
		$this->add_menu_items();

		add_filter( 'plugin_action_links', array( $this, 'plugin_action_links' ), 10, 2 );

		$this->add_translation_queue_hooks();

		$this->add_translation_in_progress_warning( $pagenow );

		add_action( 'wpml_support_page_after', array( $this, 'add_com_log_link' ) );
	}

	private function add_non_theme_customizer_hooks( $pagenow ) {
		if ( $pagenow !== 'customize.php' ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
			add_action( 'admin_print_styles', array( $this, 'admin_print_styles' ), 11 );
			$this->add_custom_xml_config();
		}
	}

	private function add_custom_xml_config() {
		$hooks = wpml_tm_custom_xml_ui_hooks();
		if ( $hooks ) {
			$hooks->init();
		}
	}

	private function add_menu_items() {
		add_action( 'wpml_admin_menu_configure', array( $this, 'translators_menu' ) );
		add_action( 'wpml_admin_menu_configure', array( $this, 'settings_menu' ) );
	}

	private function add_translation_queue_hooks() {
		if ( \WPML\UIPage::isTranslationQueue( $_GET ) ) {
			$this->tm_queue = make(\WPML_Translations_Queue::class);
			$this->tm_queue->init_hooks();
		}
	}

	public function render_prepared_translation_queue_editor(): bool {
		if (
			isset( $this->tm_queue )
			&& $this->tm_queue instanceof WPML_Translations_Queue
			&& $this->tm_queue->is_editor_prepared()
		) {
			$this->tm_queue->display();
			return true;
		}
		return false;
	}

	private function disableAllNonWPMLNotices() {
		if ( \WPML\UIPage::isTMDashboard( $_GET ) ) {
			add_action( 'admin_head', function () {
				if ( ! apply_filters( 'wpml_tm_lock_ui', false ) ) {
					remove_all_actions( 'admin_notices' );
					wpml_get_admin_notices()->add_admin_notices_action();

					foreach ( (array) apply_filters( 'wpml_tm_dashboard_notices', [] ) as $notice ) {
						if ( is_callable( $notice ) ) {
							add_action( 'admin_notices', $notice );
						}
					}
				}
			}, 1 );
		}
	}

	private function get_required_cap_based_on_current_user_role() {
		$capability = User::CAP_MANAGE_TRANSLATION_MANAGEMENT;
		if ( User::hasCap( User::CAP_ADMINISTRATOR ) ) {
			$capability = User::CAP_ADMINISTRATOR;
		} else if ( User::hasCap( User::CAP_MANAGE_TRANSLATIONS ) ) {
			$capability = User::CAP_MANAGE_TRANSLATIONS;
		}

		return $capability;
	}
}
