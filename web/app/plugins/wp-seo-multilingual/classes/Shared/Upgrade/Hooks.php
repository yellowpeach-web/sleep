<?php

namespace WPML\WPSEO\Shared\Upgrade;

use WPML\Utilities\Lock;
use WPML\WPSEO\Shared\Options;

class Hooks implements \IWPML_Backend_Action {

	const LOCK_NAME = 'wpml-seo-upgrade';

	const KEY_LAST_UPGRADE_HASH = 'last-upgrade-hash';

	public function add_hooks() {
		add_action( 'init', [ $this, 'init' ] );
	}

	public function init() {
		if ( ! wp_doing_ajax() && $this->needsUpgrade() ) {
			Lock::whileLocked( self::LOCK_NAME, MINUTE_IN_SECONDS, [ $this, 'run' ] );
		}
	}

	private function needsUpgrade() {
		return Options::get( self::KEY_LAST_UPGRADE_HASH ) !== CommandsProvider::getHash();
	}

	public function run() {
		CommandsProvider::get()->each(
			function ( $commandClass ) {
				call_user_func( [ $commandClass, 'run' ] );
			}
		);

		Options::set( self::KEY_LAST_UPGRADE_HASH, CommandsProvider::getHash() );
	}
}
