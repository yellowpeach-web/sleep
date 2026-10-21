<?php

namespace WPML\Media;

class ActivateHandleMediaAutoOnStActivation implements \IWPML_Backend_Action, \IWPML_DIC_Action {
	private $activateHandleMediaAuto;

	public function __construct( ActivateHandleMediaAuto $activateHandleMediaAuto ) {
		$this->activateHandleMediaAuto = $activateHandleMediaAuto;
	}

	public function add_hooks() {
		add_action( 'admin_init', [ $this, 'maybeActivate' ] );
	}

	public function maybeActivate() {
		if (
			Option::shouldEnableHandleMediaAutoOnStActivation() &&
			defined( 'WPML_ST_VERSION' )
		) {
			if ( Option::shouldHandleMediaAuto() ) {
				Option::removeShouldEnableHandleMediaAutoOnStActivation();
			} else {
				$this->activateHandleMediaAuto->activate();
			}
		}
	}
}
