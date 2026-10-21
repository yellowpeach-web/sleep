<?php

namespace WPML\TM\Settings;

use WPML\WP\OptionManager;

class PreferenceReapplyConsent {

	const SETTING = 'consented-custom-fields-to-translate';

	public static function record( array $fieldNames ) {
		if ( ! $fieldNames ) {
			return;
		}

		OptionManager::update(
			'TM',
			self::SETTING,
			array_values( array_unique( array_merge( self::all(), array_values( $fieldNames ) ) ) )
		);
	}

	public static function consume( array $fieldNames ): array {
		$consented = array_values( array_intersect( self::all(), $fieldNames ) );

		if ( $consented ) {
			OptionManager::update( 'TM', self::SETTING, array_values( array_diff( self::all(), $consented ) ) );
		}

		return $consented;
	}

	public static function all(): array {
		return (array) OptionManager::getOr( [], 'TM', self::SETTING );
	}
}
