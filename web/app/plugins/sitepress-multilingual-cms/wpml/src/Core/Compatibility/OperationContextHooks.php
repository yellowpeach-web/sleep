<?php

namespace WPML\Core\Compatibility;

class OperationContextHooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	public function add_hooks() {
		add_filter( 'wpml_is_operation', [ $this, 'isOperation' ], 10, 2 );
		add_filter( 'wpml_current_operation', [ $this, 'currentOperation' ] );
	}

	public function isOperation( $default, $operation ) {
		return is_string( $operation ) && OperationContext::is( $operation );
	}

	public function currentOperation( $default ) {
		return OperationContext::current();
	}
}
