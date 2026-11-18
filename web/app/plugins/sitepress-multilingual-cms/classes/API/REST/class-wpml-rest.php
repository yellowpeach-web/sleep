<?php

class WPML_Rest {

	public function is_available() {
		return function_exists( 'rest_get_server' );
	}

	public function is_rest_request() {
		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}

	public function has_registered_routes() {
		return (bool) rest_get_server()->get_routes();
	}
}
