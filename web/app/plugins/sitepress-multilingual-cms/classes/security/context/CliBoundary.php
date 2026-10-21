<?php

namespace WPML\Security\Context;

use WPML\Core\Security\ExecutionContext\CliProof;
use WPML\Core\Security\ExecutionContext\ExecutionContext;

final class CliBoundary {

	public static function resolve( ?callable $isCli = null ) {
		$isCli = $isCli ?: 'wpml_is_cli';

		if ( ! is_callable( $isCli ) || ! $isCli() ) {
			return null;
		}

		return ExecutionContext::trusted( CliProof::forProcess( 'wp-cli' ) );
	}
}
