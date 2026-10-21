<?php

class WPML_TM_Page {

	public static function is_tm_dashboard() {
		return self::is_dashboard();
	}

	public static function is_tm_translators() {
		return WPML_TM_Subview_Detection::is_on_main_or_settings_page()
			   && 'translators' === WPML_TM_Subview_Detection::current_subview();
	}

	public static function is_settings() {
		return WPML_TM_Subview_Detection::is_on_settings_page()
			   && self::subview_is( '', 'mcsetup' );
	}

	public static function is_translation_queue() {
		return ( WPML_TM_Subview_Detection::is_on_main_page()
				 && 'tasks' === WPML_TM_Subview_Detection::current_subview() )
			   || WPML_TM_Subview_Detection::is_on_legacy_queue_page();
	}

	public static function is_translation_editor_page() {
		return self::is_translation_queue() && isset( $_GET['job_id'] );
	}

	public static function is_job_list() {
		return WPML_TM_Subview_Detection::is_on_main_page()
			   && 'jobs' === WPML_TM_Subview_Detection::current_subview();
	}

	public static function is_dashboard() {
		return WPML_TM_Subview_Detection::is_on_main_page()
			   && self::subview_is( '', 'dashboard' );
	}

	public static function is_notifications_page() {
		return WPML_TM_Subview_Detection::is_on_main_page()
			   && 'notifications' === WPML_TM_Subview_Detection::current_subview();
	}

	public static function get_translators_url( $params = array() ) {
		$url               = admin_url( 'admin.php?page=' . self::get_tm_folder() . WPML_Translation_Management::PAGE_SLUG_SETTINGS );
		$params['section'] = 'translators';

		return add_query_arg( $params, $url );
	}

	private static function subview_is( ...$slugs ) {
		return in_array( WPML_TM_Subview_Detection::current_subview(), $slugs, true );
	}

	private static function get_tm_folder() {
		return defined( 'WPML_TM_FOLDER' ) ? WPML_TM_FOLDER : '';
	}
}
