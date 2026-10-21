<?php

namespace WPML\LanguageSwitcher;

class SubmenuIds {

	private static $reserved = [];

	public static function reserve( string $base ): string {
		$unique = $base;

		for ( $n = 2; isset( self::$reserved[ $unique ] ); $n++ ) {
			$unique = $base . '-' . $n;
		}

		self::$reserved[ $unique ] = true;

		return $unique;
	}

	public static function reset(): void {
		self::$reserved = [];
	}
}
