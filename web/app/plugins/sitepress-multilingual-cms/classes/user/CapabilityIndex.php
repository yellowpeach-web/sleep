<?php

namespace WPML\User;

use WPML\LIB\WP\User;
use WPML\Upgrade\Commands\BackfillCapabilityIndex;
use WPML\Upgrade\CommandsStatus;

class CapabilityIndex {

	const META_VALUE = '1';

	const VERIFIED_EMPTY_PREFIX = 'wpml_capability_index_empty_';

	public static function capabilities() {
		return [ User::CAP_TRANSLATE, User::CAP_MANAGE_TRANSLATIONS ];
	}

	public static function metaKey( $capability, $table_prefix ) {
		return '_' . $table_prefix . 'wpml_cap_' . $capability;
	}

	public static function capabilitiesMetaKey( $table_prefix ) {
		return $table_prefix . 'capabilities';
	}

	public static function isReady() {
		return ( new CommandsStatus() )->hasBeenExecuted( BackfillCapabilityIndex::class );
	}

	public static function requestRebuild() {
		( new CommandsStatus() )->clearExecuted( BackfillCapabilityIndex::class );
		BackfillCapabilityIndex::resetPosition();
		self::clearVerifiedEmpty();
	}

	public static function verifiedEmptyOption( $capability ) {
		return self::VERIFIED_EMPTY_PREFIX . $capability;
	}

	public static function isVerifiedEmpty( $capability ) {
		return (bool) get_option( self::verifiedEmptyOption( $capability ), false );
	}

	public static function markVerifiedEmpty( $capability ) {
		update_option( self::verifiedEmptyOption( $capability ), 1, false );
	}

	public static function clearVerifiedEmpty() {
		foreach ( self::capabilities() as $capability ) {
			delete_option( self::verifiedEmptyOption( $capability ) );
		}
	}

	public static function forgetVerifiedEmpty( $capability ) {
		if ( self::isVerifiedEmpty( $capability ) ) {
			delete_option( self::verifiedEmptyOption( $capability ) );
		}
	}

	public static function sync( $user_id, $capabilities, $table_prefix ) {
		$capabilities = maybe_unserialize( $capabilities );
		$capabilities = is_array( $capabilities ) ? $capabilities : [];

		foreach ( self::capabilities() as $capability ) {
			if ( self::grants( $capabilities, $capability ) ) {
				update_user_meta( $user_id, self::metaKey( $capability, $table_prefix ), self::META_VALUE );
				self::forgetVerifiedEmpty( $capability );
			} else {
				delete_user_meta( $user_id, self::metaKey( $capability, $table_prefix ) );
			}
		}
	}

	public static function forget( $user_id, $table_prefix ) {
		foreach ( self::capabilities() as $capability ) {
			delete_user_meta( $user_id, self::metaKey( $capability, $table_prefix ) );
		}
	}

	public static function grants( array $capabilities, $capability ) {
		return array_key_exists( $capability, $capabilities ) && (bool) $capabilities[ $capability ];
	}
}
