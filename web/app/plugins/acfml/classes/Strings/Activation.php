<?php

namespace ACFML\Strings;

class Activation {

	const OPTION_BACK_FILL_PENDING = 'acfml_strings_back_fill_pending';

	public static function activate(): void {
		update_option( self::OPTION_BACK_FILL_PENDING, 1, true );
	}

	public static function isBackFillPending(): bool {
		$pending = get_option( self::OPTION_BACK_FILL_PENDING, null );

		if ( null === $pending ) {
			self::clearBackFillPending();

			return false;
		}

		return (bool) $pending;
	}

	public static function clearBackFillPending(): void {
		update_option( self::OPTION_BACK_FILL_PENDING, 0, true );
	}
}
