<?php

use WPML\Infrastructure\WordPress\Component\SettingsStorage\VirtualSettingsOption;

class WPML_URL_Format {

	const SETTING = 'language_negotiation_type';

	const DEFAULT_FORMAT = 1;

	public static function of( $settings ) {
		if ( is_array( $settings ) && isset( $settings[ self::SETTING ] ) ) {
			return (int) $settings[ self::SETTING ];
		}

		return self::DEFAULT_FORMAT;
	}

	public static function restoreWhenLost() {
		VirtualSettingsOption::restoreLostKeyTo( self::SETTING, self::DEFAULT_FORMAT );
	}
}
