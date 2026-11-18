<?php

namespace WPML\PB\Media;

class MediaIds {

	const SIZE_SUFFIX_PATTERN = '(?:\|\w[\w-]*)?';

	public static function isList( $value ): bool {
		return is_string( $value ) && 1 === preg_match( '/^\s*\d+' . self::SIZE_SUFFIX_PATTERN . '(?:\s*,\s*\d+' . self::SIZE_SUFFIX_PATTERN . ')*\s*$/', $value );
	}

	public static function translate( string $value, callable $translate_id ): string {
		$ids = explode( ',', $value );

		foreach ( $ids as &$id ) {
			$id = preg_replace_callback(
				'/^\s*(\d+)(\|\w[\w-]*)?/',
				function ( array $matches ) use ( $translate_id ) {
					return $translate_id( (int) $matches[1] ) . ( $matches[2] ?? '' );
				},
				$id
			);
		}

		return implode( ',', $ids );
	}
}
