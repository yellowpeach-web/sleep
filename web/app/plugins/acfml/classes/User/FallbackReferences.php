<?php

namespace ACFML\User;

use ACFML\Field\RelationalIds;

class FallbackReferences {

	const MARKER_PREFIX = 'user:';

	public static function isUserMarker( string $marker ): bool {
		return 0 === strpos( $marker, self::MARKER_PREFIX );
	}

	public static function createMarker( int $userId, string $languageCode, string $fieldName ): string {
		return self::MARKER_PREFIX . $userId . ':' . $languageCode . ':' . $fieldName;
	}

	public function repointBeforeSave( $value, int $userId, array $field, string $languageCode ) {
		$defaultValue = get_metadata( 'user', $userId, $field['name'], true );

		foreach ( (array) $value as $id ) {
			if ( ! is_numeric( $id ) ) {
				continue;
			}

			$translatedId = RelationalIds::getTranslatedId( (int) $id, $field, $languageCode );
			if ( $translatedId && $translatedId !== (int) $id ) {
				$value = RelationalIds::replaceFallback( $value, $defaultValue, (int) $id, $translatedId );
			} elseif ( ! $translatedId && self::isListedIn( $id, $defaultValue ) ) {
				RelationalIds::addFallbackMarker( $field, (int) $id, self::createMarker( $userId, $languageCode, $field['name'] ) );
			}
		}

		return $value;
	}

	public function repoint( string $marker, int $referencedId, int $translatedId, string $languageCode ): bool {
		list( , $userId, $markerLanguageCode, $fieldName ) = array_pad( explode( ':', $marker, 4 ), 4, '' );

		if ( $markerLanguageCode !== $languageCode ) {
			return false;
		}

		$languageKey  = LanguageValueHooks::getLanguageKey( $fieldName, $languageCode );
		$value        = get_metadata( 'user', (int) $userId, $languageKey, true );
		$defaultValue = get_metadata( 'user', (int) $userId, $fieldName, true );
		$newValue     = RelationalIds::replaceFallback( $value, $defaultValue, $referencedId, $translatedId );

		return $newValue === $value || false !== update_metadata( 'user', (int) $userId, $languageKey, $newValue, $value );
	}

	private static function isListedIn( $id, $value ): bool {
		return in_array( (string) $id, array_map( 'strval', array_filter( (array) $value, 'is_scalar' ) ), true );
	}
}
