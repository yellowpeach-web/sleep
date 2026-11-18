<?php

namespace WPML\TM\ATE\Handshake;

use WPML\TM\ATE\PullDelivery\State as PullState;

class Plan {

	const BACKGROUND = 'background';
	const SPAWNED    = 'spawned';
	const HELP       = 'help';

	const LOOPBACK_USER_AGENT = 'WPML-ATE-Delivery/1.0 (site loopback; +https://wpml.org/documentation/automatic-translation/)';

	private $finishesEarly;

	private $loopbackWorks;

	public function __construct( $finishesEarly = null, $loopbackWorks = null ) {
		$this->finishesEarly = $finishesEarly ?: [ self::class, 'phpFinishesEarly' ];
		$this->loopbackWorks = $loopbackWorks ?: [ self::class, 'loopbackVerdict' ];
	}

	public static function decide() {
		return ( new self() )->choose();
	}

	public function choose() {
		if ( call_user_func( $this->finishesEarly ) ) {
			return self::BACKGROUND;
		}

		return call_user_func( $this->loopbackWorks ) ? self::SPAWNED : self::HELP;
	}

	public static function phpFinishesEarly() {
		return (bool) apply_filters( 'wpml_ate_handshake_php_finishes_early', self::runsOnPhpFpm() );
	}

	public static function runsOnPhpFpm() {
		return function_exists( 'fastcgi_finish_request' ) && 'fpm-fcgi' === PHP_SAPI;
	}

	public static function loopbackVerdict() {
		self::reprobeIfSpawnUnanswered();

		$known = PullState::getKey( 'loopback_ok' );

		if ( is_bool( $known ) ) {
			return $known;
		}

		$response = wp_remote_post(
			Routes::url( Routes::COLLECT ),
			[
				'timeout'    => 5,
				'blocking'   => true,
				'sslverify'  => false,
				'user-agent' => self::LOOPBACK_USER_AGENT,
				'body'       => [ 'token' => 'probe' ],
			]
		);

		$ok = self::probeReachedWpml( $response );

		PullState::recordLoopbackProbe( $ok );

		return $ok;
	}

	public static function probeReachedWpml( $response ) {
		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code >= 200 && $code < 300 ) {
			return true;
		}

		if ( 403 !== $code ) {
			return false;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		return is_array( $body ) && isset( $body['data']['status'] );
	}

	public static function reprobeIfSpawnUnanswered() {
		$spawnedAt = (int) State::getKey( 'spawned_at' );

		if ( $spawnedAt < 1 ) {
			return false;
		}

		$bound = Gate::seconds();

		if ( $spawnedAt + $bound >= Clock::now() ) {
			return false;
		}

		$verdict = PullState::getKey( 'loopback_ok' );

		PullState::update( [ 'loopback_ok' => null ] );
		State::update( [ 'spawned_at' => 0 ] );

		Log::write(
			'loopback_reprobe',
			[
				'spawned_at' => $spawnedAt,
				'bound_s'    => $bound,
				'verdict'    => $verdict,
			]
		);

		return true;
	}
}
