<?php

namespace WPML\TM\ATE\SiteName;

use WPML\TM\ATE\Log\Entry;
use WPML\TM\ATE\Log\EventsTypes;
use WPML\TM\ATE\Log\Storage;
use function WPML\Container\make;

class Sender {

	public static function send() {
		$credentials = self::credentialsFingerprint();

		if ( null === $credentials ) {
			return;
		}

		try {
			$title = SiteName::read();

			$result = make( \WPML_TM_AMS_API::class )->updateSiteName( $title );

			if ( true !== $result ) {
				Record::noteAttempt( $credentials );
				self::log( is_wp_error( $result ) ? $result->get_error_message() : 'unexpected answer' );

				return;
			}

			Record::noteAcknowledged( $title, $credentials );
		} catch ( \Throwable $e ) {
			Record::noteAttempt( $credentials );
			self::log( $e->getMessage() );
		}
	}

	public static function sendIfStale() {
		$credentials = self::credentialsFingerprint();

		if ( null === $credentials ) {
			return;
		}

		if ( ! Record::isDue( SiteName::read(), $credentials ) ) {
			return;
		}

		self::send();
	}

	public static function credentialsFingerprint() {
		$data = get_option( \WPML_TM_ATE_Authentication::AMS_DATA_KEY, [] );
		$data = is_array( $data ) ? $data : [];

		if ( empty( $data['secret'] ) || empty( $data['shared'] ) ) {
			return null;
		}

		return substr( hash( 'sha256', (string) $data['shared'] ), 0, 16 );
	}

	private static function log( $error ) {
		$entry              = Entry::createForType( EventsTypes::SERVER_AMS, [ 'error' => $error ] );
		$entry->description = 'site_name was not stored on the AMS website record';

		Storage::add( $entry, true );
	}
}
