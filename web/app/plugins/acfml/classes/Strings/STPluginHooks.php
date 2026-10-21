<?php

namespace ACFML\Strings;

use ACFML\Options;
use WPML\LIB\WP\Hooks;

class STPluginHooks implements \IWPML_Backend_Action {

	const PLUGIN_STATUS_KEY = 'string-translation-status';

	const PLUGIN_STATUS_ACTIVATED   = 'activated';
	const PLUGIN_STATUS_DEACTIVATED = 'deactivated';

	private $backFill;

	public function __construct( BackFill $backFill ) {
		$this->backFill = $backFill;
	}

	public function add_hooks() {
		if ( wp_doing_ajax() ) {
			return;
		}

		Hooks::onAction( 'wp_loaded' )
			->then( [ $this, 'maybeRegisterMissingStrings' ] );
	}

	public function maybeRegisterMissingStrings() {
		$isStActivated           = HooksFactory::isStActivated();
		$isPluginStatusActivated = self::getPluginStatus() === self::PLUGIN_STATUS_ACTIVATED;

		if ( ! $isStActivated ) {
			if ( $isPluginStatusActivated ) {
				self::setPluginStatus( self::PLUGIN_STATUS_DEACTIVATED );
			}
			return;
		}

		if ( ! $isPluginStatusActivated || Activation::isBackFillPending() ) {
			$this->backFill->registerMissing();
			BackFill::markDone();
			self::setPluginStatus( self::PLUGIN_STATUS_ACTIVATED );
			Activation::clearBackFillPending();
			$this->backFill->copyOnce();
			return;
		}

		$this->backFill->runOnce();

		$this->backFill->copyOnce();
	}

	private static function getPluginStatus() {
		return Options::get( self::PLUGIN_STATUS_KEY );
	}

	private static function setPluginStatus( $status ) {
		Options::set( self::PLUGIN_STATUS_KEY, $status );
	}
}
