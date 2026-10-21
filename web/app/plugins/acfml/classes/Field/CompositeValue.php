<?php

namespace ACFML\Field;

use WPML\FP\Obj;

class CompositeValue {

	const SEPARATOR = ':';

	const SUB_KEYS = [
		'link' => [ 'title', 'url' ],
	];

	public static function getSubKeys( $field ) {
		return self::getSubKeysForType( Obj::prop( 'type', $field ) );
	}

	public static function getSubKeysForType( $type ) {
		return (array) Obj::propOr( [], (string) $type, self::SUB_KEYS );
	}

	public static function getTexts( $field, $value ) {
		if ( ! is_array( $value ) ) {
			return [];
		}

		$texts = [];
		foreach ( self::getSubKeys( $field ) as $subKey ) {
			$text = Obj::prop( $subKey, $value );
			if ( is_string( $text ) && '' !== $text ) {
				$texts[ $subKey ] = $text;
			}
		}

		return $texts;
	}

	public static function asStringField( array $field, $flatName, $subKey ) {
		$field['name'] = $flatName . self::SEPARATOR . $subKey;

		return $field;
	}

	public static function splitName( $name ) {
		$position = strpos( (string) $name, self::SEPARATOR );

		if ( false === $position ) {
			return [ (string) $name, null ];
		}

		return [ substr( $name, 0, $position ), substr( $name, $position + 1 ) ];
	}

	public static function merge( $storedValue, $subKey, $text ) {
		if ( ! is_array( $storedValue ) || ! array_key_exists( $subKey, $storedValue ) ) {
			return null;
		}

		$storedValue[ $subKey ] = $text;

		return $storedValue;
	}
}
