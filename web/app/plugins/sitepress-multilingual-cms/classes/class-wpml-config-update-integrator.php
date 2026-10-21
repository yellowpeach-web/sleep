<?php

class WPML_Config_Update_Integrator {
	private $log;
	private $worker;
	private $deactivated_plugins = array();

	public function __construct( WPML_Log $log, ?WPML_Config_Update $worker = null ) {
		$this->log    = $log;
		$this->worker = $worker;
	}

	public function get_worker() {
		if ( null === $this->worker ) {
			global $sitepress;
			$http         = new WP_Http();
			$this->worker = new WPML_Config_Update( $sitepress, $http, $this->log );
		}

		return $this->worker;
	}

	public function set_worker( WPML_Config_Update $worker ) {
		$this->worker = $worker;
	}

	public function add_hooks() {
		add_action( 'update_wpml_config_index', array( $this, 'update_event_cron' ) );
		\WPML\Request\Adapter\Ajax::register( 'update_wpml_config_index', \WPML\Request\Policy\Policy::capability( [ 'wpml_manage_translation_options', 'wpml_manage_theme_and_plugin_localization', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( 'icl_theme_plugins_compatibility_nonce', '_icl_nonce' ) ), array( $this, 'update_event_ajax' ) );
		add_action( 'after_switch_theme', array( $this, 'update_event' ) );
		add_action( 'activated_plugin', array( $this, 'update_event' ) );
		add_action( 'wpml_setup_completed', array( $this, 'update_event' ) );
		add_action( 'wpml_refresh_remote_xml_config', array( $this, 'update_event' ) );
		add_action( 'wp_loaded', array( $this, 'deferred_import' ) );

		add_action( 'activated_plugin', array( $this, 'local_config_event' ) );
		add_action( 'deactivated_plugin', array( $this, 'deactivated_plugin_event' ) );
		add_action( 'after_switch_theme', array( $this, 'local_config_event' ) );
		add_action( 'upgrader_process_complete', array( $this, 'local_config_event' ) );
	}

	public function local_config_event() {
		$this->get_worker()->defer_local_config_import();
	}

	public function deactivated_plugin_event( $plugin ) {
		$this->deactivated_plugins[] = $plugin;

		$this->get_worker()->defer_local_config_import( $this->deactivated_plugins );
	}

	public function deferred_import() {
		if ( wp_installing() ) {
			return;
		}

		if ( ! is_admin() && ! wp_doing_cron() && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		$this->get_worker()->run_deferred_import();
	}

	public function update_event() {
		$this->get_worker()
			 ->run();
	}

	public function update_event_ajax() {
		$nonce = isset( $_POST['_icl_nonce'] ) ? sanitize_text_field( $_POST['_icl_nonce'] ) : '';

		if ( ! wp_verify_nonce( $nonce, 'icl_theme_plugins_compatibility_nonce' ) ) {
			/* translators: Error message returned when a request from the browser cannot be trusted and is turned away. */
			wp_send_json_error( esc_html__( 'Invalid request!', 'sitepress' ), 400 );
			return;
		}

		if ( $this->get_worker()
				  ->run() ) {
			echo esc_html( self::last_checked_text() );
		}

		die;
	}

	public static function last_checked_text() {
		$updated = (int) get_option( 'wpml_config_index_updated' );

		if ( $updated <= 0 ) {
			/* translators: Shown in place of the date on the "Last checked" line of the WPML config index when the index was never fetched on this site. */
			return __( 'Never', 'sitepress' );
		}

		return date_i18n( /* translators: A date pattern, not words: the letters stand for parts of the date (month, day, year) as PHP writes them. Change only the order and the punctuation to suit your language; never translate the letters. */ __( 'F j, Y', 'sitepress' ), $updated )
			. ' '
			. date_i18n( /* translators: A time pattern, not words: the letters stand for parts of the time (hour, minute, am/pm, time zone) as PHP writes them. Change only the order and the punctuation to suit your language; never translate the letters. */ __( 'g:i a T', 'sitepress' ), $updated );
	}

	public function update_event_cron() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$this->update_event();
	}
}
