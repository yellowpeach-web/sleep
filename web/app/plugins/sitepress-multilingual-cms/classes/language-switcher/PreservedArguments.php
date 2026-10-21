<?php

namespace WPML\LanguageSwitcher;

class PreservedArguments {

	public static function encode( array $args ): array {
		$encoded = [];

		foreach ( $args as $key => $value ) {
			$encoded[ rawurlencode( (string) $key ) ] = is_array( $value )
				? self::encode( $value )
				: rawurlencode( (string) $value );
		}

		return $encoded;
	}
}
