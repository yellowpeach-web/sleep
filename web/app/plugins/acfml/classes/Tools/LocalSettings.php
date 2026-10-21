<?php

namespace ACFML\Tools;

use WPML\FP\Obj;

class LocalSettings {

	const SCAN_LOCAL_FILES = 'acfml_tools_local_settings_scan_files';

	public static function shouldRunScan() {
		$storedSetting = (bool) get_option( self::SCAN_LOCAL_FILES, defined( 'ACFML_SCAN_LOCAL_FIELDS' ) && constant( 'ACFML_SCAN_LOCAL_FIELDS' ) );
		if ( $storedSetting ) {
			return true;
		}

		$scanOnce = is_admin()
			&& 'acf-tools' === Obj::prop( 'page', $_GET )
			&& LocalUI::SCAN_MODE_ONCE === Obj::prop( LocalUI::POST_SCAN_MODE, $_POST );
		if ( $scanOnce ) {
			return true;
		}

		return false;
	}

	public static function enableScanMode( $enabled ) {
		update_option( self::SCAN_LOCAL_FILES, (bool) $enabled );
	}

	public static function getScanMode() {
		$storedSetting =  (bool) get_option( self::SCAN_LOCAL_FILES, defined( 'ACFML_SCAN_LOCAL_FIELDS' ) && constant( 'ACFML_SCAN_LOCAL_FIELDS' ) );
		if ( $storedSetting ) {
			return LocalUI::SCAN_MODE_ALWAYS;
		}

		return LocalUI::SCAN_MODE_NONE;
	}
}
