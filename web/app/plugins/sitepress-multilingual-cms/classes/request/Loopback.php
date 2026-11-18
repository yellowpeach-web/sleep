<?php

namespace WPML\Request;

final class Loopback {

	const QUICK_MS = 50;

	const ANSWER_WITHIN = 30;

	const OFF_FOR = 604800;

	const STATE_OPTION = 'wpml_quick_loopback_state';

	public static function post( $url, array $args, $name ) {
		$options = self::quickAllowed( $name ) ? self::quickCurlOptions() : [];

		if ( ! $options ) {
			return wp_remote_post( $url, $args );
		}

		self::markSpawned( $name );

		$bound = function ( $handle, $request = null, $requestUrl = null ) use ( $url, $options ) {
			if ( $requestUrl === $url ) {
				self::setOptions( $handle, $options );
			}
		};

		self::hook( $bound );

		try {
			return wp_remote_post( $url, $args );
		} finally {
			self::unhook( $bound );
		}
	}

	public static function answered( $name ) {
		$state = self::state();

		if ( empty( $state[ $name ]['spawned_at'] ) ) {
			return;
		}

		$state[ $name ]['spawned_at'] = 0;
		self::saveState( $state );
	}

	public static function quickCurlOptions() {
		if ( ! function_exists( 'curl_version' ) || ! defined( 'CURLOPT_TIMEOUT_MS' ) || ! defined( 'CURLOPT_NOSIGNAL' ) || ! defined( 'CURL_VERSION_ASYNCHDNS' ) ) {
			return [];
		}

		$version = curl_version();

		if ( ! is_array( $version ) || ! ( (int) $version['features'] & CURL_VERSION_ASYNCHDNS ) ) {
			return [];
		}

		return [
			CURLOPT_NOSIGNAL   => true,
			CURLOPT_TIMEOUT_MS => self::QUICK_MS,
		];
	}

	private static function quickAllowed( $name ) {
		$state = self::state();
		$entry = self::entry( $state, $name );
		$now   = time();

		if ( $entry['off_at'] > 0 ) {
			if ( $entry['off_at'] > $now - self::OFF_FOR ) {
				return false;
			}

			$entry['off_at'] = 0;
			$state[ $name ]  = $entry;
			self::saveState( $state );
		}

		if ( $entry['spawned_at'] > 0 && $entry['spawned_at'] < $now - self::ANSWER_WITHIN ) {
			$state[ $name ] = [
				'spawned_at' => 0,
				'off_at'     => $now,
			];
			self::saveState( $state );

			return false;
		}

		return true;
	}

	private static function markSpawned( $name ) {
		$state = self::state();
		$entry = self::entry( $state, $name );

		if ( $entry['spawned_at'] > 0 ) {
			return;
		}

		$entry['spawned_at'] = time();
		$state[ $name ]      = $entry;
		self::saveState( $state );
	}

	private static function entry( array $state, $name ) {
		$entry = isset( $state[ $name ] ) && is_array( $state[ $name ] ) ? $state[ $name ] : [];

		return [
			'spawned_at' => isset( $entry['spawned_at'] ) ? (int) $entry['spawned_at'] : 0,
			'off_at'     => isset( $entry['off_at'] ) ? (int) $entry['off_at'] : 0,
		];
	}

	private static function state() {
		$state = get_option( self::STATE_OPTION, [] );

		return is_array( $state ) ? $state : [];
	}

	private static function saveState( array $state ) {
		update_option( self::STATE_OPTION, $state, false );
	}

	protected static function hook( callable $callback ) {
		add_action( 'http_api_curl', $callback, PHP_INT_MAX, 3 );
	}

	protected static function unhook( callable $callback ) {
		remove_action( 'http_api_curl', $callback, PHP_INT_MAX );
	}

	protected static function setOptions( $handle, array $options ) {
		curl_setopt_array( $handle, $options );
	}
}
