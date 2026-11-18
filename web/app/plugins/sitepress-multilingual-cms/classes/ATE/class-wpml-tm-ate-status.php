<?php

use WPML\Setup\Option;
use WPML\TM\ATE\AutomaticTranslationCapabilities;

class WPML_TM_ATE_Status {

	public static function is_account_usable() {
		return AutomaticTranslationCapabilities::isAvailable();
	}

	public static function is_editor_ate() {
		return \WPML\TranslationPolicy\Policy\Policy::isSiteEditorAte();
	}

	public static function is_ate_account_wanted() {
		return \WPML\TranslationPolicy\Policy\Policy::siteWantsAteAccount();
	}

	public static function can_ate_job_exist() {
		return self::is_account_usable() || self::is_editor_ate();
	}

	public static function is_enabled() {
		return wpml_get_tm_sub_setting( 'doc_translation_method', null ) === ICL_TM_TMETHOD_ATE;
	}

	public static function is_active() {
		if ( Option::isTMAllowed() ) {
			$ams_data = get_option( WPML_TM_ATE_Authentication::AMS_DATA_KEY, array() );
			$ams_data = is_array( $ams_data ) ? $ams_data : array();

			if ( array_key_exists( 'status', $ams_data ) ) {
				return $ams_data['status'] === WPML_TM_ATE_Authentication::AMS_STATUS_ACTIVE;
			}
		}

		return false;
	}

	public static function is_enabled_and_activated() {
		return self::is_enabled() && self::is_active();
	}
}
