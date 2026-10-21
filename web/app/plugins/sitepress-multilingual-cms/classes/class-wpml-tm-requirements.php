<?php

class WPML_TM_Requirements {

	private $missing_one;

	public function __construct() {
		$this->missing_one = false;
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded_action' ), 999999 );
	}

	private function check_required_plugins() {
		$this->missing_one = false;

		if ( ! defined( 'ICL_SITEPRESS_VERSION' )
				 || ICL_PLUGIN_INACTIVE
				 || version_compare( ICL_SITEPRESS_VERSION, '2.0.5', '<' )
		) {
			$this->missing_one     = true;
		}
	}

	public function plugins_loaded_action() {
		$this->check_required_plugins();

		if ( ! $this->missing_one ) {
			do_action( 'wpml_tm_has_requirements' );
		}
	}
}
