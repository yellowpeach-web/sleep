<?php

namespace WPML\Comments\Counts;

class FeatureSwitch {

	const FILTER = 'wpml_comment_counts_per_language';

	private static $on = null;

	public static function readOnInit() {
		self::$on = self::ask();

		return self::$on;
	}

	public static function isOn() {
		return null === self::$on ? self::ask() : self::$on;
	}

	public static function isLoadedAndOn() {
		return true === self::$on;
	}

	public static function resetRequestCache() {
		self::$on = null;
	}

	private static function ask() {
		return (bool) apply_filters( self::FILTER, true );
	}
}
