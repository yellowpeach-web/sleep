<?php

namespace WPML\Security\Context;

use WPML\Core\Security\ExecutionContext\CronProof;
use WPML\Core\Security\ExecutionContext\ExecutionContext;

final class CronBoundary {

	public static function resolve( ?callable $isCron = null ) {
		$isCron = $isCron ?: ( function_exists( 'wp_doing_cron' ) ? 'wp_doing_cron' : null );

		if ( null === $isCron || ! is_callable( $isCron ) || ! $isCron() ) {
			return null;
		}

		return ExecutionContext::trusted( CronProof::forScheduledRun( 'wp-cron' ) );
	}
}
