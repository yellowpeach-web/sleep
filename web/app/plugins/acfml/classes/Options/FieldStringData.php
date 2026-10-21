<?php

namespace ACFML\Options;

use ACFML\Strings\Package;
use WPML\FP\Obj;

class FieldStringData {

	public static function of( $field, $value ) {
		return [
			'namespace' => Package::OPTION_PACKAGE_NAMESPACE,
			'id'        => Obj::prop( 'name', $field ),
			'key'       => Obj::prop( 'key', $field ),
			'title'     => Obj::prop( 'label', $field ),
			'type'      => self::getValueType( $value ),
		];
	}

	private static function getValueType( $value ) {
		$type = 'LINE';
		if ( is_array( $value ) ) {
			$type = 'array';
		} elseif ( strip_tags( $value ) !== $value ) {
			$type = 'VISUAL';
		} elseif ( strpos( $value, "\n" ) !== false ) {
			$type = 'AREA';
		}
		return $type;
	}
}
