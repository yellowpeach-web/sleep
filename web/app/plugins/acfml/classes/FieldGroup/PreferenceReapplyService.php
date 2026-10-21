<?php

namespace ACFML\FieldGroup;

class PreferenceReapplyService {

	const CLASS_NAME = '\WPML\TM\Settings\PreferenceReapply';

	public static function get(): ?object {
		return self::make( self::CLASS_NAME );
	}

	public static function make( string $className ): ?object {
		if ( ! class_exists( $className ) ) {
			return null;
		}

		try {
			return \WPML\Container\make( $className );
		} catch ( \Throwable $e ) {
			return null;
		}
	}
}
