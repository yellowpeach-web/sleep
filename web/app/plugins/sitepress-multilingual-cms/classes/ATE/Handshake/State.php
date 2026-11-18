<?php

namespace WPML\TM\ATE\Handshake;

class State {

	const OPTION = 'wpml_ate_handshake_state';

	private static $heldNonces = [];

	private static $shutdownHooked = false;

	public static function defaults() {
		return [
			'last_wake_received_at'  => 0,
			'last_wake_plan'         => '',
			'last_drive_at'          => 0,
			'last_admin_load_at'     => 0,
			'last_front_poll_at'     => 0,
			'last_run_at'            => 0,
			'last_run_trigger'       => '',
			'last_run_work_left'     => false,
			'work_left_opened_at'    => 0,
			'sent_uncollected_since' => 0,
			'oldest_automatic_sent_at' => 0,
			'in_flight'              => 0,
			'in_flight_at'           => 0,
			'spawned_at'             => 0,
			'handshake_accepted_at'  => 0,
			'handshake_refused_at'     => 0,
			'handshake_refused_route'  => '',
			'handshake_refused_status' => 0,
			'keys_refused_at'          => 0,
			'keys_refused_answer'      => '',
			'corrections_count'        => 0,
			'corrections_pages'        => 0,
			'corrections_batch_id'     => '',
			'corrections_reason'       => '',
			'corrections_read_at'      => 0,
			'nonces'                 => [],
		];
	}

	public static function spawnChanges() {
		return (int) self::getKey( 'spawned_at' ) > 0 ? [] : [ 'spawned_at' => Clock::now() ];
	}

	public static function get() {
		$stored = get_option( self::OPTION, [] );

		return array_merge( self::defaults(), is_array( $stored ) ? array_intersect_key( $stored, self::defaults() ) : [] );
	}

	public static function getKey( $key ) {
		$state = self::get();

		return array_key_exists( $key, $state ) ? $state[ $key ] : null;
	}

	public static function update( array $changes ) {
		$state = array_merge( self::get(), array_intersect_key( $changes, self::defaults() ) );

		$nonces          = is_array( $state['nonces'] ) ? $state['nonces'] : [];
		$state['nonces'] = self::pruneNonces( array_replace( $nonces, self::$heldNonces ) );

		update_option( self::OPTION, $state, true );

		self::$heldNonces = [];

		return $state;
	}

	public static function nonceSeen( $nonce ) {
		$nonces = self::get()['nonces'];
		$nonces = array_replace( is_array( $nonces ) ? $nonces : [], self::$heldNonces );

		return isset( $nonces[ $nonce ] ) && (int) $nonces[ $nonce ] > Clock::now();
	}

	public static function rememberNonce( $nonce, $ttl ) {
		self::$heldNonces[ (string) $nonce ] = Clock::now() + (int) $ttl;

		if ( ! self::$shutdownHooked && function_exists( 'add_action' ) ) {
			self::$shutdownHooked = true;
			add_action( 'shutdown', [ self::class, 'writeHeldNonces' ], 0 );
		}
	}

	public static function writeHeldNonces() {
		if ( self::$heldNonces ) {
			self::update( [] );
		}
	}

	public static function resetForTests() {
		self::$heldNonces     = [];
		self::$shutdownHooked = false;
	}

	public static function recordInFlight( $count, $oldestAt = 0, $oldestAutomaticAt = null ) {
		return self::update( self::inFlightChanges( $count, $oldestAt, $oldestAutomaticAt ) );
	}

	public static function recordInFlightSummary( array $summary ) {
		return self::update( self::inFlightSummaryChanges( $summary ) );
	}

	public static function inFlightSummaryChanges( array $summary, $count = null ) {
		return self::inFlightChanges(
			null === $count ? (int) ( isset( $summary['count'] ) ? $summary['count'] : 0 ) : (int) $count,
			isset( $summary['oldest_at'] ) ? (int) $summary['oldest_at'] : 0,
			isset( $summary['oldest_automatic_at'] ) ? (int) $summary['oldest_automatic_at'] : null
		);
	}

	public static function inFlightChanges( $count, $oldestAt = 0, $oldestAutomaticAt = null ) {
		$count   = max( 0, (int) $count );
		$changes = [
			'in_flight'    => $count,
			'in_flight_at' => Clock::now(),
		];

		if ( 0 === $count ) {
			$changes['oldest_automatic_sent_at'] = 0;
		} elseif ( null !== $oldestAutomaticAt ) {
			$changes['oldest_automatic_sent_at'] = max( 0, (int) $oldestAutomaticAt );
		}

		if ( 0 === $count ) {
			$changes['sent_uncollected_since'] = 0;
		} elseif ( ! (int) self::getKey( 'sent_uncollected_since' ) ) {
			$changes['sent_uncollected_since'] = $oldestAt > 0 ? (int) $oldestAt : Clock::now();
		}

		return $changes;
	}

	private static function pruneNonces( array $nonces ) {
		$now = Clock::now();

		return array_filter(
			$nonces,
			function ( $expiry ) use ( $now ) {
				return (int) $expiry > $now;
			}
		);
	}
}
