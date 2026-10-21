<?php

namespace WPML\TM\ATE\API;

class IdentityRequestTimeout {

	const DEFAULT_SECONDS = 30;

	public static function seconds() {
		return (int) \apply_filters( 'wpml_ate_identity_request_timeout', self::DEFAULT_SECONDS );
	}
}
