<?php

namespace WPML\PostHog;

use WPML\Core\Component\PostHog\Application\Service\CountDashboardSessionService;

class StopOptOutOnTeaEnabled {

	public static function handle( $enabled = false ) {
		if ( ! $enabled ) {
			return;
		}

		global $wpml_dic;

		if ( ! $wpml_dic ) {
			return;
		}

		try {
			$service = $wpml_dic->make( CountDashboardSessionService::class );
			$service->stopForTeaEnabled();
		} catch ( \Throwable $e ) {
		}
	}

}
