<?php

namespace WPML\PostHog;

use WPML\Core\Component\PostHog\Application\Service\CountDashboardSessionService;

class RetryPendingOptOutStop {

	public static function handle() {
		global $wpml_dic;

		if ( ! $wpml_dic ) {
			return;
		}

		try {
			$service = $wpml_dic->make( CountDashboardSessionService::class );
			$service->retryPendingStop();
		} catch ( \Throwable $e ) {
		}
	}

}
