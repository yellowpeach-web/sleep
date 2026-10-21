<?php

namespace WPML\Action;


class Type {

	private $backend_actions  = [ 'IWPML_Backend_Action_Loader', 'IWPML_Backend_Action' ];
	private $frontend_actions = [ 'IWPML_Frontend_Action_Loader', 'IWPML_Frontend_Action' ];
	private $ajax_actions     = [ 'IWPML_AJAX_Action_Loader', 'IWPML_AJAX_Action' ];
	private $rest_actions     = [ 'IWPML_REST_Action_Loader', 'IWPML_REST_Action' ];
	private $cli_actions      = [ 'IWPML_CLI_Action_Loader', 'IWPML_CLI_Action' ];
	private $dic_actions      = [ 'IWPML_DIC_Action' ];

	private $implementations;

	public function __construct( $class_name ) {
		$implementations = class_exists( $class_name ) || interface_exists( $class_name )
			? class_implements( $class_name )
			: false;

		if ( ! is_array( $implementations ) ) {
			error_log( sprintf( 'WPML: action class "%s" could not be loaded and was skipped.', $class_name ) );
			$implementations = array();
		}

		$this->implementations = $implementations;
	}

	public function is( $type ) {
		$action_type = $type . '_actions';
		return $this->has_implementation( $this->$action_type );
	}

	private function has_implementation( $interfaces ) {
		return count( array_intersect( $this->implementations, $interfaces ) ) > 0;
	}

}
