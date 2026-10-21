<?php

namespace WPML\TM\Settings;

use WPML\Core\Component\CustomFieldPreferences\Domain\ElementType;
use WPML\Infrastructure\WordPress\Component\CustomFieldPreferences\ContainerFreeServices;

class PreferenceWriter {

	public static function setModes( string $type, array $nameToMode ): bool {
		if ( ! $nameToMode ) {
			return true;
		}
		if ( ! ContainerFreeServices::preferenceWriter()->setModes( $type, $nameToMode ) ) {
			$tm      = wpml_get_setting( 'translation-management' );
			$tm      = is_array( $tm ) ? $tm : [];
			$blobKey = ElementType::BLOB_KEYS[ $type ];
			foreach ( $nameToMode as $name => $mode ) {
				$tm[ $blobKey ][ $name ] = (int) $mode;
			}
			icl_set_setting( 'translation-management', $tm, true );
		}
		self::patchLiveTm( $type, $nameToMode );

		return true;
	}

	public static function deleteNames( string $type, array $names ) {
		if ( ! $names ) {
			return true;
		}
		if ( ! ContainerFreeServices::preferenceWriter()->deleteNames( $type, $names ) ) {
			$tm      = wpml_get_setting( 'translation-management' );
			$tm      = is_array( $tm ) ? $tm : [];
			$blobKey = ElementType::BLOB_KEYS[ $type ];
			foreach ( $names as $name ) {
				unset( $tm[ $blobKey ][ $name ] );
			}
			icl_set_setting( 'translation-management', $tm, true );
		}
		self::unpatchLiveTm( $type, $names );

		return true;
	}

	private static function patchLiveTm( string $type, array $nameToMode ) {
		$tm = isset( $GLOBALS['iclTranslationManagement'] ) ? $GLOBALS['iclTranslationManagement'] : null;
		if ( ! $tm instanceof \TranslationManagement || ! $tm->settings_loaded() ) {
			return;
		}
		$blobKey = ElementType::BLOB_KEYS[ $type ];
		foreach ( $nameToMode as $name => $mode ) {
			$tm->settings[ $blobKey ][ $name ] = (int) $mode;
		}
	}

	private static function unpatchLiveTm( string $type, array $names ) {
		$tm = isset( $GLOBALS['iclTranslationManagement'] ) ? $GLOBALS['iclTranslationManagement'] : null;
		if ( ! $tm instanceof \TranslationManagement || ! $tm->settings_loaded() ) {
			return;
		}
		$blobKey = ElementType::BLOB_KEYS[ $type ];
		foreach ( $names as $name ) {
			unset( $tm->settings[ $blobKey ][ $name ] );
		}
	}
}
