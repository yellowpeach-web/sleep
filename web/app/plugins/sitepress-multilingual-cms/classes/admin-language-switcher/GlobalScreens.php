<?php

namespace WPML\AdminLanguageSwitcher;

class GlobalScreens {

	const SCREENS = [ 'widgets.php', 'options-permalink.php' ];

	public static function isCurrent() {
		$pagenow = isset( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : null;

		return in_array( $pagenow, self::SCREENS, true );
	}
}
