<?php

namespace ACFML\Options;

use ACFML\Field\CompositeValue;

class TranslatedValue {

	public static function toStore( $translatedValue, $subKey, $sourceOptionName, $optionName ) {
		if ( null === $subKey ) {
			return $translatedValue;
		}

		$storedValue = get_option( $optionName );

		return CompositeValue::merge(
			is_array( $storedValue ) ? $storedValue : get_option( $sourceOptionName ),
			$subKey,
			$translatedValue
		);
	}
}
