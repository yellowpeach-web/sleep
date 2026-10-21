<?php

namespace WPML\Upgrade;

class FrontEndScopeGate {

	public static function isSettledFrontEndRequest( $loaderId ) {
		if ( is_admin() || wpml_is_cli() ) {
			return false;
		}

		return ( new CommandsStatus() )->isFrontEndScopeSettled( ICL_SITEPRESS_VERSION, $loaderId );
	}
}
