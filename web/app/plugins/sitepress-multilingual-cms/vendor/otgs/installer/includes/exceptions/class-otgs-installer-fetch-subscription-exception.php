<?php

use OTGS\Installer\Api\Exception\InvalidSubscription;
use OTGS\Installer\Api\Exception\ServiceUnavailable;

class OTGS_Installer_Fetch_Subscription_Exception extends Exception {

	private $evidence = '';

	public function set_evidence( $evidence ) {
		$this->evidence = (string) $evidence;
	}

	public function get_evidence() {
		return $this->evidence;
	}

	public static function is_site_key_refused( Exception $exception ) {
		$api_error = self::api_error( $exception );

		return $api_error && $api_error->refuses_key();
	}

	public static function api_error_text( Exception $exception ) {
		$api_error = self::api_error( $exception );

		return $api_error ? $api_error->get_details() : '';
	}

	private static function api_error( Exception $exception ) {
		if ( $exception instanceof self && $exception->getPrevious() instanceof InvalidSubscription ) {
			return $exception->getPrevious();
		}

		return null;
	}

	public static function is_service_unavailable( Exception $exception ) {
		return $exception instanceof self && $exception->getPrevious() instanceof ServiceUnavailable;
	}
}
