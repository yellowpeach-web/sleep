<?php

namespace WPML\PB\Elementor\Helper;

class ElementTree {

	public static function map( $elements, callable $visit ) {
		if ( ! is_array( $elements ) ) {
			return $elements;
		}

		foreach ( $elements as &$element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			$element = $visit( $element );

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = self::map( $element['elements'], $visit );
			}
		}
		unset( $element );

		return $elements;
	}
}
