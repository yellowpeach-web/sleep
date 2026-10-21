<?php

use WPML\FP\Fns;
use WPML\LIB\WP\Cache;

class WPML_TM_Rest_Jobs_Translation_Service {

	const CACHE_GROUP = 'wpml-tm-services';

	const SERVICE_TTL = 3600;

	const UNREACHABLE_TTL = 60;

	public function get_name( $service_id ) {
		$name = '';
		if ( is_numeric( $service_id ) ) {
			$service = $this->get_translation_service( (int) $service_id );
			if ( $service ) {
				$name = $service->name;
			} else {
				$name = (string) $service_id;
			}
		} else {
			/* translators: Shown in place of the name of a translation service when the translation is done on this site instead of being sent away. */
			$name = __( 'Local', 'sitepress' );
		}

		return $name;
	}

	private function get_translation_service( $service_id ) {
		$unreachable_key = $this->get_unreachable_key( $service_id );

		if ( ! Fns::isNothing( Cache::get( self::CACHE_GROUP, $unreachable_key ) ) ) {
			return null;
		}

		$getService = function ( $service_id ) {
			$current_service = TranslationProxy::get_current_service();
			if ( $current_service && $current_service->id === $service_id ) {
				return $current_service;
			} else {
				return TranslationProxy_Service::get_service( $service_id );
			}
		};

		$cachedGetService = Cache::memorize( self::CACHE_GROUP, self::SERVICE_TTL, $getService );

		try {
			return $cachedGetService( $service_id );
		} catch ( WPMLTranslationProxyApiException $e ) {
			WPML_TranslationProxy_Com_Log::log_error(
				sprintf(
					'Could not resolve the name of translation service %s; falling back to its id.',
					$service_id
				)
			);

			Cache::set( self::CACHE_GROUP, $unreachable_key, self::UNREACHABLE_TTL, true );

			return null;
		}
	}

	private function get_unreachable_key( $service_id ) {
		return 'unreachable-' . $service_id;
	}
}
