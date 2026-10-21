<?php

namespace WPML\TM\ATE\SiteName;

class Record {

	const OPTION = 'wpml_tm_ams_site_name';

	const RETRY_AFTER = DAY_IN_SECONDS;

	const RETRY_CAP = 7 * DAY_IN_SECONDS;

	public static function acknowledged( $credentials ) {
		return self::acknowledgedIn( self::readFor( $credentials ) );
	}

	public static function noteAcknowledged( $title, $credentials ) {
		self::write(
			[
				'credentials'  => (string) $credentials,
				'acknowledged' => (string) $title,
				'attempted_at' => time(),
				'refusals'     => 0,
			]
		);
	}

	public static function noteAttempt( $credentials ) {
		$record                 = self::readFor( $credentials );
		$record['credentials']  = (string) $credentials;
		$record['refusals']     = self::refusalsIn( $record ) + 1;
		$record['attempted_at'] = time();

		self::write( $record );
	}

	public static function isDue( $title, $credentials ) {
		$record = self::readFor( $credentials );

		if ( self::acknowledgedIn( $record ) === (string) $title ) {
			return false;
		}

		$attempted_at = isset( $record['attempted_at'] ) ? (int) $record['attempted_at'] : 0;

		return ( time() - $attempted_at ) >= self::waitAfter( self::refusalsIn( $record ) );
	}

	private static function acknowledgedIn( array $record ) {
		return array_key_exists( 'acknowledged', $record ) ? (string) $record['acknowledged'] : null;
	}

	private static function refusalsIn( array $record ) {
		return isset( $record['refusals'] ) ? max( 0, (int) $record['refusals'] ) : 0;
	}

	private static function waitAfter( $refusals ) {
		$wait = self::RETRY_AFTER;

		for ( $i = 1; $i < $refusals && $wait < self::RETRY_CAP; $i++ ) {
			$wait *= 2;
		}

		return min( $wait, self::RETRY_CAP );
	}

	private static function readFor( $credentials ) {
		$record = get_option( self::OPTION, [] );

		if ( ! is_array( $record ) || ! isset( $record['credentials'] ) || null === $credentials ) {
			return [];
		}

		return (string) $record['credentials'] === (string) $credentials ? $record : [];
	}

	private static function write( array $record ) {
		update_option( self::OPTION, $record, true );
	}
}
