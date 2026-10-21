<?php

use WPML\Core\Twig_Loader_Filesystem;
use WPML\Core\Twig_Environment;
use WPML\TM\Settings\RequestSettings;

class WPML_Integrations_Requirements {
	const NOTICE_GROUP          = 'requirements';
	const CORE_REQ_NOTICE_ID    = 'core-requirements';
	const MISSING_REQ_NOTICE_ID = 'missing-requirements';
	const EDITOR_NOTICE_ID      = 'enable-translation-editor';

	private $issues      = array();
	private $should_create_editor_notice = false;
	private $integrations;
	private $requirements_scripts;

	private $sitepress;

	private $third_party_dependencies;

	private $requirements_notification;

	public function __construct(
		SitePress $sitepress,
		?WPML_Third_Party_Dependencies $third_party_dependencies = null,
		?WPML_Requirements_Notification $requirements_notification = null,
		?array $integrations = null
	) {
		$this->sitepress                 = $sitepress;
		$this->third_party_dependencies  = $third_party_dependencies;
		$this->requirements_notification = $requirements_notification;
		$this->integrations              = $integrations ? $integrations : $this->get_integrations();
	}

	public function init_hooks() {
		if ( $this->sitepress->get_setting( 'setup_complete' ) ) {
			add_action( 'admin_init', array( $this, 'init' ) );
			\WPML\Request\Adapter\Ajax::register( 'wpml_set_translation_editor', \WPML\Request\Policy\Policy::capability( [ 'wpml_manage_translation_options', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( 'wpml_set_translation_editor', 'nonce' ) ), array( $this, 'set_translation_editor_callback' ) );
		}
	}

	public function init() {
		if ( $this->sitepress->get_wp_api()->is_back_end() ) {
			$this->update_issues();
			$this->update_notices();

			if ( $this->issues ) {
				$requirements_scripts = $this->get_requirements_scripts();
				$requirements_scripts->add_plugins_activation_hook();
			}
		}
	}

	private function update_notices() {
		$wpml_admin_notices = wpml_get_admin_notices();

		$wpml_admin_notices->remove_notice( self::NOTICE_GROUP, self::CORE_REQ_NOTICE_ID );

		if ( ! $this->issues ) {
			$wpml_admin_notices->remove_notice( self::NOTICE_GROUP, self::MISSING_REQ_NOTICE_ID );
		}

		if ( ! $this->should_create_editor_notice ) {
			$wpml_admin_notices->remove_notice( self::NOTICE_GROUP, self::EDITOR_NOTICE_ID );
		}

		if ( $this->issues || $this->should_create_editor_notice ) {

			$notice_model = $this->get_notice_model();
			$wp_api       = $this->sitepress->get_wp_api();

			$this->add_requirements_notice( $notice_model, $wpml_admin_notices, $wp_api );
			$this->add_tm_editor_notice( $notice_model, $wpml_admin_notices, $wp_api );
		}

	}

	private function update_issues() {
		$this->issues      = $this->get_third_party_dependencies()->get_issues();
		$this->update_should_create_editor_notice();
	}

	private function update_should_create_editor_notice() {
		$doc_translation_method = wpml_get_tm_sub_setting( 'doc_translation_method', null );
		$editor_translation_set =
			null !== $doc_translation_method &&
			in_array(
				(string) $doc_translation_method,
				array(
					(string) ICL_TM_TMETHOD_EDITOR,
					(string) ICL_TM_TMETHOD_ATE,
				),
				true
			);
		$requires_tm_editor     = false;

		foreach ( $this->integrations as $integration_item ) {
			if ( in_array( 'wpml-translation-editor', $integration_item['notices-display'], true ) ) {
				$requires_tm_editor = true;
				break;
			}
		}

		$this->should_create_editor_notice = ! $editor_translation_set && ! $this->issues && $requires_tm_editor;
	}

	public function set_translation_editor_callback() {
		if ( ! $this->is_valid_request() ) {
			wp_send_json_error( __( 'This action is not allowed', 'sitepress' ) );
		} else {
			$wpml_admin_notices = wpml_get_admin_notices();

			$tm_settings = RequestSettings::load()['translation-management'] ?? array();
			$tm_settings = is_array( $tm_settings ) ? $tm_settings : array();

			$tm_settings['doc_translation_method'] = 1;
			$this->sitepress->set_setting( 'translation-management', $tm_settings, true );
			$this->sitepress->set_setting( 'doc_translation_method', 1, true );

			$wpml_admin_notices->remove_notice( self::NOTICE_GROUP, self::EDITOR_NOTICE_ID );

			wp_send_json_success();
		}
	}

	private function is_valid_request() {
		$valid_request = true;
		if ( ! array_key_exists( 'nonce', $_POST ) ) {
			$valid_request = false;
		}
		if ( $valid_request ) {
			$nonce = $_POST['nonce'];

			$nonce_is_valid = wp_verify_nonce( $nonce, 'wpml_set_translation_editor' );
			if ( ! $nonce_is_valid ) {
				$valid_request = false;
			}
		}

		return $valid_request && current_user_can( 'manage_options' );
	}

	private function get_integrations() {
		$integrations = new WPML_Integrations( $this->sitepress->get_wp_api() );

		return $integrations->get_results();
	}

	private function get_integrations_names( $notice_type ) {
		$names = array();

		foreach ( $this->integrations as $integration ) {
			if ( in_array( $notice_type, $integration['notices-display'], true ) &&
				 ! in_array( $integration['name'], $names, true ) ) {
				$names[] = $integration['name'];
			}
		}

		return $names;
	}

	private function get_notice_model() {
		if ( ! $this->requirements_notification ) {
			$template_paths   = array(
				WPML_PLUGIN_PATH . '/templates/warnings/',
			);
			$twig_loader      = new Twig_Loader_Filesystem( $template_paths );
			$environment_args = array();
			if ( WP_DEBUG ) {
				$environment_args['debug'] = true;
			}
			$twig         = new Twig_Environment( $twig_loader, $environment_args );
			$twig_service = new WPML_Twig_Template( $twig );

			$this->requirements_notification = new WPML_Requirements_Notification( $twig_service );
		}

		return $this->requirements_notification;
	}

	private function add_actions_to_notice( WPML_Notice $notice ) {
		/* translators: Button label that closes a notice and keeps it from coming back. Verb, imperative. */
		$dismiss_action = new WPML_Notice_Action( __( 'Dismiss', 'sitepress' ), '#', true, false, true, false );
		$notice->add_action( $dismiss_action );
	}

	private function add_callbacks( WPML_Notice $notice, WPML_WP_API $wp_api ) {
		if ( method_exists( $notice, 'add_display_callback' ) ) {
			$notice->add_display_callback( array( __CLASS__, 'is_notice_screen' ) );
		}
	}

	public static function is_notice_screen() {
		$wp_api = new WPML_WP_API();

		return $wp_api->is_core_page() || $wp_api->is_plugins_page() || $wp_api->is_themes_page();
	}

	private function add_requirements_notice( WPML_Requirements_Notification $notice_model, WPML_Notices $wpml_admin_notices, WPML_WP_API $wp_api ) {
		if ( $this->issues ) {
			$message = $notice_model->get_message( $this->issues, 1 );

			$requirements_notice = new WPML_Notice( self::MISSING_REQ_NOTICE_ID, $message, self::NOTICE_GROUP );

			$this->add_actions_to_notice( $requirements_notice );
			$this->add_callbacks( $requirements_notice, $wp_api );
			$wpml_admin_notices->add_notice( $requirements_notice );
		}
	}

	private function add_tm_editor_notice( WPML_Requirements_Notification $notice_model, WPML_Notices $wpml_admin_notices, WPML_WP_API $wp_api ) {
		if ( $this->should_create_editor_notice ) {
			$requirements_scripts = $this->get_requirements_scripts();
			$requirements_scripts->add_translation_editor_notice_hook();

			$integrations_names = $this->get_integrations_names( 'wpml-translation-editor' );
			$text               = $notice_model->get_settings( $integrations_names );
			$notice             = new WPML_TM_Editor_Notice( self::EDITOR_NOTICE_ID, $text, self::NOTICE_GROUP );
			$notice->set_css_class_types( 'info' );

			/* translators: Button label in a notice about a setting; "it" is the setting the notice is about. Verb phrase, imperative. */
			$enable_action = new WPML_Notice_Action( _x( 'Enable it now', 'Integration requirement notice title for translation editor: enable action', 'sitepress' ), '#', false, false, true );
			$enable_action->set_js_callback( 'js-set-translation-editor' );
			$notice->add_action( $enable_action );

			$this->add_callbacks( $notice, $wp_api );
			$this->add_actions_to_notice( $notice );
			$wpml_admin_notices->add_notice( $notice );
		}
	}

	private function get_requirements_scripts() {
		if ( ! $this->requirements_scripts ) {
			return new WPML_Integrations_Requirements_Scripts();
		}

		return $this->requirements_scripts;
	}

	private function get_third_party_dependencies() {
		if ( ! $this->third_party_dependencies ) {
			$integrations                   = new WPML_Integrations( $this->sitepress->get_wp_api() );
			$requirements                   = new WPML_Requirements();
			$this->third_party_dependencies = new WPML_Third_Party_Dependencies( $integrations, $requirements );
		}

		return $this->third_party_dependencies;
	}
}
