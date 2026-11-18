<?php

namespace WPML\LanguageSwitcher;

class LandmarkLabels {

	private static $taken = [];

	private static $count = 0;

	public static function unique( string $plain, string $qualified ): string {
		self::$count++;

		$base  = 1 === self::$count ? $plain : $qualified;
		$label = $base;

		for ( $n = 2; isset( self::$taken[ $label ] ); $n++ ) {
			$label = $base . ' ' . $n;
		}

		self::$taken[ $label ] = true;

		return $label;
	}

	public static function take( string $label ): void {
		self::$count++;

		self::$taken[ $label ] = true;
	}

	public static function instances(): int {
		return self::$count;
	}

	public static function reset(): void {
		self::$taken = [];
		self::$count = 0;
	}
}
