<?php

namespace WPML\TM\API\ATE;

use WPML\Core\Component\ATE\Domain\SiteRegistration;
use WPML\FP\Either;
use WPML\FP\Fns;
use WPML\LIB\WP\WordPress;
use function WPML\Container\make;

class WebsiteContext {

	public static function getWebsiteContext() {
		$websiteContext = make( \WPML_TM_ATE_API::class )->get_website_context();

		if ( $websiteContext instanceof \WP_Error ) {
			$error = [
				'error' => $websiteContext->get_error_message(),
			];

			if ( SiteRegistration::NOT_REGISTERED_ERROR_CODE === $websiteContext->get_error_code() ) {
				$error['code'] = SiteRegistration::NOT_REGISTERED_ERROR_CODE;
			}

			return $error;
		}

		return (array) $websiteContext;
	}
}
