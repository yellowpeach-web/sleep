<?php

namespace OTGS\Installer;

class OutboundLink {

	public static function to( $url, array $args = [] ) {
		if ( class_exists( '\WPML\OutboundLinks\OutboundLinks' ) ) {
			return \WPML\OutboundLinks\OutboundLinks::to( $url, $args );
		}

		return $url;
	}
}
