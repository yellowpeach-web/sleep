<?php

use WPML\FP\Obj;

class WPML_TM_Subview_Detection {

	public static function current_subview() {
		foreach ( array( 'tab', 'section', 'sm' ) as $key ) {
			$value = Obj::propOr( '', $key, $_GET );
			if ( is_string( $value ) && '' !== $value ) {
				return $value;
			}
		}

		return '';
	}

	public static function is_on_main_page() {
		return self::is_on_page( WPML_Translation_Management::PAGE_SLUG_MANAGEMENT );
	}

	public static function is_on_settings_page() {
		return self::is_on_page( WPML_Translation_Management::PAGE_SLUG_SETTINGS );
	}

	public static function is_on_main_or_settings_page() {
		return self::is_on_main_page() || self::is_on_settings_page();
	}

	public static function is_on_legacy_queue_page() {
		return self::is_on_page( WPML_Translation_Management::PAGE_SLUG_QUEUE );
	}

	private static function is_on_page( $suffix ) {
		return is_admin()
			   && Obj::propOr( false, 'page', $_GET ) === self::tm_folder() . $suffix;
	}

	private static function tm_folder() {
		return defined( 'WPML_TM_FOLDER' ) ? WPML_TM_FOLDER : '';
	}
}
