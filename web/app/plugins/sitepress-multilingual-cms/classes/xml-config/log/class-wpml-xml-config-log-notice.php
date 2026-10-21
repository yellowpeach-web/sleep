<?php

class WPML_XML_Config_Log_Notice {
	const NOTICE_ERROR_GROUP = 'wpml-config-update';
	const NOTICE_ERROR_ID    = 'wpml-config-update-error';

	private $log;

	public function __construct( WPML_Log $log ) {
		$this->log = $log;
	}

	public function add_hooks() {
		if ( is_admin() ) {
			add_action( 'wpml_loaded', array( $this, 'refresh_notices' ) );
		}
	}

	public function refresh_notices() {
		$notices = wpml_get_admin_notices();

		if ( $this->log->is_empty() ) {
			$notices->remove_notice( self::NOTICE_ERROR_GROUP, self::NOTICE_ERROR_ID );

			return;
		}

		$text = '<p>' . esc_html__( 'WPML could not load configuration files, which your site needs.', 'sitepress' ) . '</p>';

		$notice = $notices->create_notice( self::NOTICE_ERROR_ID, $text, self::NOTICE_ERROR_GROUP );
		$notice->set_css_class_types( array( 'error' ) );

		$log_url = add_query_arg(
			array(
				'page' => WPML_Config_Update_Log::get_support_page_log_section(),
				'tool' => WPML_Config_Update_Log::get_support_page_log_tool(),
			),
			get_admin_url( null, 'admin.php' )
		);

		$show_logs = $notices->get_new_notice_action( __( 'Detailed error log', 'sitepress' ), $log_url );

		$retry_url = get_admin_url( null, 'update-core.php#icl_theme_plugins_compatibility' );
		/* translators: Button label in a notice: do the same thing once more. Verb, imperative. */
		$retry     = $notices->get_new_notice_action( __( 'Retry', 'sitepress' ), $retry_url, false, false, true );

		$notice->add_action( $show_logs );
		$notice->add_action( $retry );
		$notice->set_dismissible( true );
		$notice->set_restrict_to_page_prefixes(
			array(
				'sitepress-multilingual-cms',
				'wpml-translation-management',
				'wpml-package-management',
				'wpml-string-translation',
			)
		);

		$notice->set_restrict_to_screen_ids( array( 'dashboard', 'plugins', 'themes' ) );
		$notice->add_exclude_from_page( WPML_Config_Update_Log::get_support_page_log_section() );
		$notices->add_notice( $notice );
	}
}
