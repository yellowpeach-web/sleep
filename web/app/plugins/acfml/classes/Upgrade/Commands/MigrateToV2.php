<?php

namespace ACFML\Upgrade\Commands;

use ACFML\FieldGroup\Mode;
use ACFML\Options;
use WPML\FP\Logic;
use WPML\FP\Obj;
use WPML\FP\Relation;
use WPML\LIB\WP\Hooks;
use function WPML\FP\pipe;

class MigrateToV2 implements Command {

	const KEY = 'migrate-to-v2';

	const STATUS_FRESH            = 'fresh';
	const STATUS_OLD              = 'old';
	const STATUS_OLD_AND_NOTIFIED = 'notified';

	const VERSION_ONE_STATUSES = [ self::STATUS_OLD, self::STATUS_OLD_AND_NOTIFIED ];

	const WP_LOADED_PRIORITY = 10;

	public static function run() {
		Hooks::onAction( 'wp_loaded', self::WP_LOADED_PRIORITY )
			->then( function() {
				if ( null === Options::get( self::KEY ) ) {
					$result = self::hasGroupWithFieldPreferenceAndNoMode() ? self::STATUS_OLD : self::STATUS_FRESH;

					if ( self::STATUS_OLD === $result ) {
						$setExpertModeOnGroup  = pipe( Obj::assoc( Mode::KEY, Mode::ADVANCED ), 'acf_update_field_group' );
						$hasGroupWithInvalidId = Relation::propEq( 'ID', 0 );

						wpml_collect( acf_get_field_groups() )->reject( $hasGroupWithInvalidId )->each( $setExpertModeOnGroup );
					}

					Options::set( self::KEY, $result );
				}
			} );
	}

	private static function hasGroupWithFieldPreferenceAndNoMode() {
		$hasNoGroupMode = fn( $group ) => ! Mode::isConfigured( $group );

		$hasOneFieldPreference = function( $group ) {
			return wpml_collect( acf_get_fields( $group ) )->first( Obj::has( 'wpml_cf_preferences' ) );
		};

		$hasGroupRequirements = Logic::allPass( [
			$hasNoGroupMode,
			$hasOneFieldPreference,
		] );

		return (bool) wpml_collect( acf_get_field_groups() )->first( $hasGroupRequirements );
	}

	public static function isUpgradeFromVersionOne() {
		return in_array( Options::get( self::KEY ), self::VERSION_ONE_STATUSES, true );
	}

	public static function needsNotification() {
		if ( self::STATUS_OLD === Options::get( self::KEY ) ) {
			Options::set( self::KEY, self::STATUS_OLD_AND_NOTIFIED );
			return true;
		}

		return false;
	}
}
