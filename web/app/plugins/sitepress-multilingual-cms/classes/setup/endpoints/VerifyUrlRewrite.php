<?php

namespace WPML\Setup\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;

class VerifyUrlRewrite implements IHandler {

	public function run( Collection $data ) {
		if ( ! function_exists( 'got_url_rewrite' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}

		return Either::right( [ 'gotUrlRewrite' => (bool) got_url_rewrite() ] );
	}
}
