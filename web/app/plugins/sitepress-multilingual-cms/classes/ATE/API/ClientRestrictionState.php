<?php

namespace WPML\TM\ATE\API;

use WPML\LIB\WP\Option;

class ClientRestrictionState {

	const OPTION = 'wpml_ate_client_restriction';

	public static function save( ClientRestriction $restriction ) {
		Option::updateWithoutAutoLoad( self::OPTION, $restriction->toArray() );
	}

	public static function get() {
		wp_cache_delete( self::OPTION, 'options' );

		$data = self::read();

		return is_array( $data ) && $data ? ClientRestriction::fromArray( $data ) : null;
	}

	public static function isRestricted() {
		wp_cache_delete( self::OPTION, 'options' );

		return (bool) self::read();
	}

	public static function debtorBlock(): string {
		$restriction = self::get();

		return $restriction ? $restriction->getDebtorBlock() : '';
	}

	public static function clearIfRestricted() {
		if ( self::isRestricted() ) {
			Option::delete( self::OPTION );
		}
	}

	private static function read() {
		return Option::getOr( self::OPTION, [] );
	}
}
