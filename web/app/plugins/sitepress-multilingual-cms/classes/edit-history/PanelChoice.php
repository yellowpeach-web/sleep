<?php

namespace WPML\EditHistory;

class PanelChoice {

	const REVERT = 'revert';

	private static $choice = null;

	public static function run( string $choice, callable $write ) {
		$outer        = self::$choice;
		self::$choice = $outer ?? $choice;

		try {
			return $write();
		} finally {
			self::$choice = $outer;
		}
	}

	public static function isActive(): bool {
		return null !== self::$choice;
	}

	public static function source(): ?string {
		return self::$choice;
	}
}
