<?php

namespace ACFML\Notice;

class Activation {

	const OPTION_PENDING = 'acfml_activation_notice_pending';

	const PENDING_RESET = 'reset';

	public static function activate() {
		update_option( self::OPTION_PENDING, self::PENDING_RESET );
	}
}
