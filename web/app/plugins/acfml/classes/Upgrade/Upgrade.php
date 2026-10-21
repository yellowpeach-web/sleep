<?php

namespace ACFML\Upgrade;

use ACFML\Options;
use WPML\Utilities\Lock;

class Upgrade {

	const LOCK_NAME = 'acfml-upgrade';

	const KEY_LAST_MIGRATION_HASH = 'last-migration-hash';

	public static function init() {
		if ( self::canUpgrade() && self::needsUpgrade() ) {
			Lock::whileLocked( self::LOCK_NAME, 2 * MINUTE_IN_SECONDS, [ __CLASS__, 'run' ] );
		}
	}

	private static function canUpgrade() {
		if ( wp_doing_ajax() ) {
			return false;
		}

		if ( wp_doing_cron() ) {
			return false;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}

		return is_admin();
	}

	private static function needsUpgrade() {
		return Options::get( self::KEY_LAST_MIGRATION_HASH ) !== CommandsProvider::getHash();
	}

	public static function run() {
		CommandsProvider::get()
			->each( function( $commandClass ) {
				call_user_func( [ $commandClass, 'run' ] );
			} );

		Options::set( self::KEY_LAST_MIGRATION_HASH, CommandsProvider::getHash() );
	}
}
