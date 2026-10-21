<?php

namespace WPML\PB;

class App {

	public static function run() {
		global $sitepress, $wpdb;

		LegacyIntegration::load();

		if (
			$sitepress->is_setup_complete()
			&& has_action( 'wpml_before_init', 'load_wpml_st_basics' )
		) {
			self::loadTMHooks( $sitepress );

			$app = new \WPML_Page_Builders_App( new \WPML_Page_Builders_Defined() );
			$app->add_hooks();

			new \WPML_PB_Loader( new \WPML_ST_Settings() );
		}
	}

	public static function loadTMHooks( $sitepress ) {
		if ( ! defined( 'WPML_TM_VERSION' ) ) {
			return;
		}

		$page_builder_hooks = new \WPML_TM_Page_Builders_Hooks(
			new \WPML_TM_Page_Builders( $sitepress ),
			$sitepress
		);

		$page_builder_hooks->init_job_data_hooks();

		if ( self::isAdminContext() ) {
			$page_builder_hooks->init_admin_hooks();
		}
	}

	private static function isAdminContext() {
		return is_admin()
			|| ( defined( 'XMLRPC_REQUEST' ) && constant( 'XMLRPC_REQUEST' ) )
			|| wpml_is_rest_request()
			|| ( defined( 'DOING_CRON' ) && constant( 'DOING_CRON' ) );
	}
}
