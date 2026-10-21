<?php

use OTGS\Installer\Api\Exception\InvalidSubscription;
use OTGS\Installer\Api\Exception\ServiceUnavailable;

class OTGS_Installer_Fetch_Subscription_Exception extends Exception {

	public static function is_site_key_refused( Exception $exception ) {
		return $exception instanceof self && $exception->getPrevious() instanceof InvalidSubscription;
	}

	public static function is_service_unavailable( Exception $exception ) {
		return $exception instanceof self && $exception->getPrevious() instanceof ServiceUnavailable;
	}
}
