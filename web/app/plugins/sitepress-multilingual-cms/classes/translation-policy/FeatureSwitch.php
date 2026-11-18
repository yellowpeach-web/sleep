<?php

namespace WPML\TranslationPolicy;

class FeatureSwitch {

	public const CONSTANT = 'WPML_TRANSLATION_POLICY_ENABLED';

	public const LATCH_KEY = 'translation_policy_cutover';

	public const DEFAULT_ON = false;

	public const CONSTANT_ABSENT = 'absent';

	public const CONSTANT_ON = 'on';

	public const CONSTANT_OFF = 'off';

	public const CONSTANT_INVALID = 'invalid';

	public const PRIORITY_AFTER_SETTINGS_RELOAD = -PHP_INT_MAX + 2;

	private static $armed = null;

	private static $on = null;

	private static $onOverride = null;

	private static $constantOverride = null;

	public static function isArmed(): bool {
		if ( null === self::$armed ) {
			$state       = self::constantState();
			self::$armed = self::CONSTANT_ABSENT === $state ? self::DEFAULT_ON : self::CONSTANT_ON === $state;
		}

		return self::$armed;
	}

	public static function isOn(): bool {
		if ( null !== self::$onOverride ) {
			return self::$onOverride;
		}

		if ( null === self::$on ) {
			self::$on = '' !== self::cutoverVersion();
		}

		return self::$on;
	}

	public static function cutoverVersion(): string {
		$latch = wpml_get_tm_sub_setting( self::LATCH_KEY, '' );

		return is_string( $latch ) ? $latch : '';
	}

	public static function constantState(): string {
		[ $defined, $value ] = self::readConstant();

		if ( ! $defined ) {
			return self::CONSTANT_ABSENT;
		}

		if ( in_array( $value, [ true, 1, '1' ], true ) ) {
			return self::CONSTANT_ON;
		}

		if ( in_array( $value, [ false, 0, '0', '' ], true ) ) {
			return self::CONSTANT_OFF;
		}

		return self::CONSTANT_INVALID;
	}

	public static function resetRequestCache(): void {
		self::$armed = null;
		self::$on    = null;
	}

	public static function overrideForTests( ?bool $on ): void {
		self::$onOverride = $on;
	}

	public static function overrideConstantForTests( ?array $definedAndValue ): void {
		self::$constantOverride = $definedAndValue;
		self::$armed            = null;
	}

	private static function readConstant(): array {
		return self::$constantOverride ?? ( defined( self::CONSTANT ) ? [ true, constant( self::CONSTANT ) ] : [ false, null ] );
	}
}
