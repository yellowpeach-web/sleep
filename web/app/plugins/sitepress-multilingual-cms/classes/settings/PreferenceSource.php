<?php

namespace WPML\TM\Settings;

require_once __DIR__ . '/../../inc/constants-since-5-0.php';

use WPML\Core\Component\CustomFieldPreferences\Domain\ElementType;

class PreferenceSource {

	const FILTER = 'wpml_custom_field_preference_source';

	public static function getSource( string $metaKey, string $elementType ): ?array {
		$settingIndex = self::settingIndex( $elementType );
		if ( null === $settingIndex || '' === $metaKey ) {
			return null;
		}

		$index = wpml_get_tm_sub_setting( $settingIndex, array() );
		if ( ! is_array( $index ) || ! isset( $index[ $metaKey ] ) || ! is_array( $index[ $metaKey ] ) ) {
			return null;
		}

		$entry = $index[ $metaKey ];
		if ( ! isset( $entry['kind'] ) || ! is_string( $entry['kind'] ) || '' === $entry['kind'] ) {
			return null;
		}

		$file = isset( $entry['file'] ) && is_string( $entry['file'] ) && '' !== $entry['file']
			? $entry['file']
			: null;

		return array(
			'kind' => $entry['kind'],
			'file' => $file,
		);
	}

	public static function filter( $source, $metaKey = '', $elementType = ElementType::POST ) {
		if ( null !== $source ) {
			return is_array( $source ) ? $source : null;
		}

		return self::getSource( (string) $metaKey, (string) $elementType );
	}

	private static function settingIndex( string $elementType ): ?string {
		switch ( $elementType ) {
			case ElementType::POST:
				return WPML_POST_META_SOURCE_SETTING_INDEX;

			case ElementType::TERM:
				return WPML_TERM_META_SOURCE_SETTING_INDEX;

			default:
				return null;
		}
	}
}
