<?php

namespace WPML\UrlHandling\SubsiteClash;

class ReservedNames implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	public function add_hooks() {
		if ( is_multisite() && ! is_subdomain_install() ) {
			add_filter( 'subdirectory_reserved_names', [ $this, 'addDirectoryCodes' ] );
		}
	}

	public function addDirectoryCodes( $names ) {
		if ( ! is_array( $names ) ) {
			return $names;
		}

		$codes = Detector::reservedCodes();
		if ( ! $codes ) {
			return $names;
		}

		return array_values( array_unique( array_merge( $names, $codes ) ) );
	}
}
