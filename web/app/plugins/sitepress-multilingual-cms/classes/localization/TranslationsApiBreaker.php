<?php

namespace WPML\Localization;

use WP_Error;

class TranslationsApiBreaker {

	const WINDOW_SECONDS = 600;

	const UNREACHABLE_SINCE_OPTION = 'wpml_translations_api_unreachable_since';

	const ERROR_CODE = 'translations_api_unreachable';

	const API_HOST = 'api.wordpress.org';

	private static $armed = 0;

	private static $unreachable = false;

	public static function arm() {
		self::$armed++;

		if ( 1 === self::$armed ) {
			add_filter( 'translations_api', array( __CLASS__, 'shortCircuit' ), 10, 3 );
			add_filter( 'translations_api_result', array( __CLASS__, 'recordResult' ), 10, 3 );
			add_filter( 'pre_http_request', array( __CLASS__, 'shortCircuitRequest' ), 10, 3 );
			add_action( 'http_api_debug', array( __CLASS__, 'recordRequestResult' ), 10, 5 );
		}
	}

	public static function disarm() {
		if ( self::$armed < 1 ) {
			return;
		}

		self::$armed--;

		if ( 0 === self::$armed ) {
			remove_filter( 'translations_api', array( __CLASS__, 'shortCircuit' ), 10 );
			remove_filter( 'translations_api_result', array( __CLASS__, 'recordResult' ), 10 );
			remove_filter( 'pre_http_request', array( __CLASS__, 'shortCircuitRequest' ), 10 );
			remove_action( 'http_api_debug', array( __CLASS__, 'recordRequestResult' ), 10 );
		}
	}

	public static function shortCircuit( $res, $type = null, $args = null ) {
		if ( false !== $res ) {
			return $res;
		}

		if ( self::$unreachable || self::isWindowLive() ) {
			return new WP_Error(
				self::ERROR_CODE,
				'WPML did not ask api.wordpress.org for this language pack: the translations API did not answer recently.'
			);
		}

		return $res;
	}

	public static function recordResult( $res, $type = null, $args = null ) {
		if ( is_wp_error( $res ) ) {
			if ( 'translations_api_failed' === $res->get_error_code() ) {
				self::$unreachable = true;
				update_option( self::UNREACHABLE_SINCE_OPTION, time(), true );
			}

			return $res;
		}

		if ( self::$unreachable || self::storedSince() > 0 ) {
			self::$unreachable = false;
			delete_option( self::UNREACHABLE_SINCE_OPTION );
		}

		return $res;
	}

	public static function shortCircuitRequest( $pre, $args = array(), $url = '' ) {
		if ( false !== $pre || ! self::isApiHost( $url ) ) {
			return $pre;
		}

		if ( self::$unreachable || self::isWindowLive() ) {
			return new WP_Error(
				self::ERROR_CODE,
				'WPML did not ask api.wordpress.org for this: the WordPress.org API did not answer recently.'
			);
		}

		return $pre;
	}

	public static function recordRequestResult( $response, $context = '', $class = '', $args = array(), $url = '' ) {
		if ( ! self::isApiHost( $url ) ) {
			return;
		}

		if ( is_wp_error( $response ) ) {
			if ( 'http_request_failed' === $response->get_error_code() ) {
				self::$unreachable = true;
				update_option( self::UNREACHABLE_SINCE_OPTION, time(), true );
			}

			return;
		}

		if ( self::$unreachable || self::storedSince() > 0 ) {
			self::$unreachable = false;
			delete_option( self::UNREACHABLE_SINCE_OPTION );
		}
	}

	public static function reset() {
		self::$armed       = 0;
		self::$unreachable = false;
	}

	private static function isWindowLive() {
		$since = self::storedSince();

		return $since > 0 && ( time() - $since ) < self::WINDOW_SECONDS;
	}

	private static function isApiHost( $url ) {
		$host = wp_parse_url( (string) $url, PHP_URL_HOST );

		return is_string( $host ) && self::API_HOST === strtolower( $host );
	}

	private static function storedSince() {
		return (int) get_option( self::UNREACHABLE_SINCE_OPTION, 0 );
	}
}

class_alias( TranslationsApiBreaker::class, 'WPML_Translations_Api_Breaker' );
