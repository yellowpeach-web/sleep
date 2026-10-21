<?php

class OTGS_Installer_Tested_Version {

	public static function for_site( $tested ) {
		return self::tested_for( $tested, self::get_wp_version() );
	}

	public static function tested_for( $tested, $wp_version ) {
		if ( ! is_string( $tested ) || '' === $tested || ! is_string( $wp_version ) ) {
			return $tested;
		}

		$release = preg_replace( '/-.*$/', '', $wp_version );

		if ( '' === $release
			|| substr_count( $tested, '.' ) >= substr_count( $release, '.' )
			|| 0 !== strpos( $release, $tested . '.' )
		) {
			return $tested;
		}

		return $release;
	}

	private static function get_wp_version() {
		return isset( $GLOBALS['wp_version'] ) && is_string( $GLOBALS['wp_version'] ) ? $GLOBALS['wp_version'] : '';
	}
}
