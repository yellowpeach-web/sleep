<?php

namespace OTGS\Installer;

/**
 * Limits how often a site asks api.wpml.org (or another repository's API) for its subscription.
 *
 * Every path that validates the site key goes through SubscriptionManager::fetch(), which runs the call through
 * this guard:
 * - one fetch per request: a second path in the same request gets the first path's answer (or its exception);
 * - after a failed or refused answer, a stored next-attempt time per repository and key: 5 minutes after the first
 *   failure, doubling on each consecutive one, at most a day. Automatic paths (the daily revalidation, the push
 *   route, a predefined key) do not ask before it; a person's explicit action (registration, "check order status",
 *   "Refresh license", an update or download) may ask once a minute inside it;
 * - a successful answer clears the stored time.
 *
 * wpmldev-10865: without it one site sent api.wpml.org twelve validations a second.
 */
class SubscriptionFetchGuard {

	const OPTION_KEY          = 'otgs_installer_subscription_fetch_backoff';
	const FIRST_DELAY         = 300;
	const MAX_DELAY           = 86400;
	const USER_RETRY_INTERVAL = 60;

	private static $fetched = [];

	public static function run( $repository_id, $site_key, $user_requested, callable $fetch ) {
		$key_hash = md5( (string) $site_key );
		$memo_key = $repository_id . '|' . $key_hash;

		if ( array_key_exists( $memo_key, self::$fetched ) ) {
			if ( self::$fetched[ $memo_key ]['exception'] ) {
				throw self::$fetched[ $memo_key ]['exception'];
			}

			return self::$fetched[ $memo_key ]['result'];
		}

		$now   = time();
		$state = self::get_state( $repository_id, $key_hash );

		if ( $state && $now < $state['next_attempt'] ) {
			$user_retry_at = $state['last_user_attempt'] + self::USER_RETRY_INTERVAL;
			if ( ! $user_requested ) {
				throw new \OTGS_Installer_Fetch_Subscription_Postponed_Exception( $state['next_attempt'] );
			}
			if ( $now < $user_retry_at ) {
				throw new \OTGS_Installer_Fetch_Subscription_Postponed_Exception( $user_retry_at );
			}
		}

		try {
			$result = $fetch();
		} catch ( \Exception $exception ) {
			self::$fetched[ $memo_key ] = [ 'result' => null, 'exception' => $exception ];
			self::record_failure( $repository_id, $key_hash, $state, $now, $user_requested );

			throw $exception;
		}

		self::$fetched[ $memo_key ] = [ 'result' => $result, 'exception' => null ];

		if ( empty( $result[0] ) ) {
			self::record_failure( $repository_id, $key_hash, $state, $now, $user_requested );
		} elseif ( $state ) {
			self::clear( $repository_id );
		}

		return $result;
	}

	public static function forget_request() {
		self::$fetched = [];
	}

	private static function get_state( $repository_id, $key_hash ) {
		$states = get_option( self::OPTION_KEY, [] );

		if ( ! is_array( $states ) || ! isset( $states[ $repository_id ]['key'] ) || $states[ $repository_id ]['key'] !== $key_hash ) {
			return null;
		}

		return array_merge(
			[ 'failures' => 0, 'next_attempt' => 0, 'last_user_attempt' => 0 ],
			$states[ $repository_id ]
		);
	}

	private static function record_failure( $repository_id, $key_hash, $state, $now, $user_requested ) {
		$failures = $state ? $state['failures'] + 1 : 1;
		$delay    = (int) min( self::MAX_DELAY, self::FIRST_DELAY * pow( 2, min( $failures - 1, 20 ) ) );

		$states = get_option( self::OPTION_KEY, [] );
		$states = is_array( $states ) ? $states : [];

		$states[ $repository_id ] = [
			'key'               => $key_hash,
			'failures'          => $failures,
			'next_attempt'      => $now + $delay,
			'last_user_attempt' => $user_requested ? $now : ( $state ? $state['last_user_attempt'] : 0 ),
		];

		update_option( self::OPTION_KEY, $states, false );
	}

	private static function clear( $repository_id ) {
		$states = get_option( self::OPTION_KEY, [] );
		if ( is_array( $states ) && isset( $states[ $repository_id ] ) ) {
			unset( $states[ $repository_id ] );
			update_option( self::OPTION_KEY, $states, false );
		}
	}
}
