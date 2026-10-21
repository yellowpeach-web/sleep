<?php

namespace WPML\PB\Media;

class MediaIds {

	public static function isList( $value ): bool {
		return is_string( $value ) && 1 === preg_match( '/^\s*\d+(?:\s*,\s*\d+)*\s*$/', $value );
	}
}
