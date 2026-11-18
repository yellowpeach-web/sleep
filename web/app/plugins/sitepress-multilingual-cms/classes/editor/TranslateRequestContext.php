<?php

namespace WPML\TM\Editor;

final class TranslateRequestContext {

	private static $depth = 0;

	public static function within( callable $callback ) {
		self::$depth ++;
		try {
			return $callback();
		} finally {
			self::$depth --;
		}
	}

	public static function isActive() {
		return self::$depth > 0;
	}

	public static function reset() {
		self::$depth = 0;
	}
}
