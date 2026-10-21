<?php

namespace ACFML\Strings;

class Activation {

	const OPTION_BACK_FILL_PENDING = 'acfml_strings_back_fill_pending';

	public static function activate(): void {
		update_option( self::OPTION_BACK_FILL_PENDING, true );
	}

	public static function isBackFillPending(): bool {
		return (bool) get_option( self::OPTION_BACK_FILL_PENDING );
	}

	public static function clearBackFillPending(): void {
		delete_option( self::OPTION_BACK_FILL_PENDING );
	}
}
