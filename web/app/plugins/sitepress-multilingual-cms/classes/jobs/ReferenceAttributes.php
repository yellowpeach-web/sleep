<?php

namespace WPML\TM\Jobs;

class ReferenceAttributes {

	const PATTERN = '/\sdata-wpml-(?:uid|created|modified)=(?:\\\\"[^"]*?\\\\"|\\\\u0022.*?\\\\u0022|&quot;.*?&quot;|&#0*34;.*?&#0*34;|"[^"]*"|\'[^\']*\'|[^\s"\'=<>`\\\\]+)/i';

	public static function strip( $value ) {
		if ( ! is_string( $value ) || false === stripos( $value, 'data-wpml-' ) ) {
			return $value;
		}

		$stripped = preg_replace( self::PATTERN, '', $value );

		return is_string( $stripped ) ? $stripped : $value;
	}
}
