<?php

use WPML\Request\Adapter\Ajax;
use WPML\Request\Policy\Policy;

abstract class WPML_TM_AJAX_Factory_Obsolete {
	protected $ajax_actions;

	protected $wpml_wp_api;


	public function __construct( &$wpml_wp_api ) {
		$this->wpml_wp_api = &$wpml_wp_api;
	}

	protected function init() {
		$this->add_ajax_actions();
	}

	protected function add_ajax_action( $handle, $callback, Policy $policy ) {
		if ( 0 === stripos( $handle, Ajax::PREFIX ) ) {
			$handle = substr( $handle, strlen( Ajax::PREFIX ) );
		}

		$this->ajax_actions[ $handle ] = array(
			'callback' => $callback,
			'policy'   => $policy,
		);
	}

	private function add_ajax_actions() {
		if ( ! $this->wpml_wp_api->is_cron_job() ) {
			foreach ( (array) $this->ajax_actions as $handle => $binding ) {

				if ( $this->wpml_wp_api->is_ajax() ) {
					Ajax::register( $handle, $binding['policy'], $binding['callback'] );
				}
				if ( $this->wpml_wp_api->is_back_end() && $this->ajax_actions ) {
					add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_resources' ) );
				}
			}
		}
	}

	abstract public function enqueue_resources( $hook_suffix );
}
