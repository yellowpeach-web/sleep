<?php

namespace WPML\Setup;

use WPML\WP\OptionManager;

class FurthestStep {

	const KEY = 'furthest-step';

	const ORDER = [
		'languages',
		'address',
		'license',
		'aiTranslation',
		'translation',
		'translationCosts',
		'translationSettings',
		'support',
		'plugins',
		'finished',
	];

	public static function indexOf( $step ) {
		$index = array_search( $step, self::ORDER, true );

		return false === $index ? -1 : $index;
	}

	public static function get() {
		$step = ( new OptionManager() )->get( Option::OPTION_GROUP, self::KEY, null );

		return is_string( $step ) && '' !== $step ? $step : null;
	}

	public static function getIndex() {
		$step = self::get();

		return null === $step ? -1 : self::indexOf( $step );
	}

	public static function advance( $step ) {
		$index   = self::indexOf( $step );
		$current = self::getIndex();

		if ( $index > $current ) {
			( new OptionManager() )->set( Option::OPTION_GROUP, self::KEY, $step );
			$current = $index;
		}

		return [
			'furthest_step'       => $current >= 0 ? self::ORDER[ $current ] : null,
			'furthest_step_index' => $current,
		];
	}
}
