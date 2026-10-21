<?php

namespace WPML\PostHog;

use WPML\Core\Component\PostHog\Application\Service\CheckPostHogShouldRecordService;

class RefreshRecording {

	public static function forceRefresh( array $context = [] ) {
		global $wpml_dic;

		if ( ! $wpml_dic ) {
			return false;
		}

		try {
			$service = $wpml_dic->make( CheckPostHogShouldRecordService::class );
			return (bool) $service->run( true, $context );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

}
