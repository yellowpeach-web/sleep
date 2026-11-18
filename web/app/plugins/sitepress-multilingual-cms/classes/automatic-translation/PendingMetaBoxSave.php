<?php

namespace WPML\TM\AutomaticTranslation\Actions;

class PendingMetaBoxSave {

	const KEY_PREFIX = 'wpml_tea_pending_meta_box_save_';

	const STORE_TTL = DAY_IN_SECONDS;

	const MARKER_TTL = 600;

	const FALLBACK_DELAY = 180;

	const CRON_HOOK = 'wpml_tea_meta_box_save_fallback';

	const UNSCHEDULE_LIMIT = 10;

	private static $answers = [];

	private static $answersRequest = null;

	public static function defer( $postId, $userId ) {
		$postId = (int) $postId;

		set_transient( self::key( $postId ), [ 'user' => (int) $userId, 'at' => time() ], self::STORE_TTL );
		self::unschedule( $postId );
		wp_schedule_single_event( time() + self::FALLBACK_DELAY, self::CRON_HOOK, [ $postId ] );

		unset( self::$answers[ $postId ] );
	}

	public static function consume( $postId ) {
		return self::take( $postId, false );
	}

	public static function consumeForFallback( $postId ) {
		return self::take( $postId, true );
	}

	private static function take( $postId, $anyAge ) {
		$postId = (int) $postId;
		self::forgetAnotherRequestsAnswers();

		if ( array_key_exists( $postId, self::$answers ) ) {
			return self::$answers[ $postId ];
		}

		$key    = self::key( $postId );
		$stored = get_transient( $key );
		$marker = null;

		if ( false !== $stored && null !== $stored ) {
			$marker = $anyAge ? self::wellFormedMarker( $stored ) : self::liveMarker( $stored );
			delete_transient( $key );
			self::unschedule( $postId );
		}

		self::$answers[ $postId ] = $marker;

		return $marker;
	}

	public static function pendingAmong( array $postIds ) {
		$keys = [];
		foreach ( $postIds as $postId ) {
			$postId = (int) $postId;
			if ( $postId > 0 ) {
				$keys[ $postId ] = self::key( $postId );
			}
		}

		if ( ! $keys ) {
			return [];
		}

		if ( wp_using_ext_object_cache() ) {
			$values  = wp_cache_get_multiple( array_values( $keys ), 'transient' );
			$pending = [];
			foreach ( $keys as $postId => $key ) {
				if ( is_array( $values ) && isset( $values[ $key ] ) && self::liveMarker( $values[ $key ] ) ) {
					$pending[] = $postId;
				}
			}

			return $pending;
		}

		global $wpdb;

		$liveAfter = time() + self::STORE_TTL - self::MARKER_TTL;
		$pending   = [];
		foreach ( $keys as $postId => $key ) {
			$timeout = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
					'_transient_timeout_' . $key
				)
			);
			if ( null !== $timeout && (int) $timeout > $liveAfter ) {
				$pending[] = $postId;
			}
		}

		return $pending;
	}

	public static function reset() {
		self::$answers        = [];
		self::$answersRequest = null;
	}

	private static function unschedule( $postId ) {
		for ( $i = 0; $i < self::UNSCHEDULE_LIMIT; $i++ ) {
			$at = wp_next_scheduled( self::CRON_HOOK, [ $postId ] );
			if ( ! $at ) {
				return;
			}
			wp_unschedule_event( $at, self::CRON_HOOK, [ $postId ] );
		}
	}

	private static function liveMarker( $stored ) {
		$marker = self::wellFormedMarker( $stored );

		if ( $marker && ( time() - $marker['at'] ) > self::MARKER_TTL ) {
			return null;
		}

		return $marker;
	}

	private static function wellFormedMarker( $stored ) {
		if ( ! is_array( $stored ) || ! isset( $stored['user'], $stored['at'] ) || ! is_numeric( $stored['user'] ) || ! is_numeric( $stored['at'] ) ) {
			return null;
		}

		return [ 'user' => (int) $stored['user'], 'at' => (int) $stored['at'] ];
	}

	private static function forgetAnotherRequestsAnswers() {
		$now = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? floatval( $_SERVER['REQUEST_TIME_FLOAT'] ) : 0.0;
		if ( $now !== self::$answersRequest ) {
			self::$answers        = [];
			self::$answersRequest = $now;
		}
	}

	private static function key( $postId ) {
		return self::KEY_PREFIX . (int) $postId;
	}
}
