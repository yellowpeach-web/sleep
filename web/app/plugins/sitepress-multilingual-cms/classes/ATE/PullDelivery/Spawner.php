<?php

namespace WPML\TM\ATE\PullDelivery;

use WPML\TM\ATE\REST\PullCollect;
use WPML\TM\Jobs\JobLog;

class Spawner {

	const TOKEN_TRANSIENT = 'wpml_pull_delivery_collect_token';

	const TOKEN_TTL = 120;

	const VIA_POST_RESPONSE = 'post_response';

	const VIA_LOOPBACK = 'loopback';

	const VIA_INLINE = 'inline';

	const VIA_ALREADY_ARRANGED = 'already_arranged';

	const VIA_BROWSER = 'browser';

	private static $handoffToken = null;

	const MISSES_BEFORE_INLINE = 2;

	const LOOPBACK_QUICK_MS = 50;

	private static $arranged = false;

	private $collector;

	private $suspectResolver;

	public function __construct( Collector $collector, SuspectResolver $suspectResolver ) {
		$this->collector       = $collector;
		$this->suspectResolver = $suspectResolver;
	}

	public static function resetForTests() {
		self::$arranged     = false;
		self::$handoffToken = null;
	}

	public function spawn() {
		if ( self::$arranged ) {
			return self::VIA_ALREADY_ARRANGED;
		}

		self::$arranged = true;

		$misses = (int) State::getKey( 'spawn_misses' );

		if ( $misses >= self::MISSES_BEFORE_INLINE ) {
			$this->logArranged( self::VIA_INLINE, $misses );
			$this->logSpawnEvent( 'pull_spawn_inline_after_misses', [ 'misses' => $misses ], true );

			$this->collect();

			return self::VIA_INLINE;
		}

		if ( function_exists( 'fastcgi_finish_request' ) ) {
			$this->logArranged( self::VIA_POST_RESPONSE, $misses );

			add_action( 'shutdown', [ $this, 'runAfterResponse' ], PHP_INT_MAX - 100 );

			return self::VIA_POST_RESPONSE;
		}

		if ( $this->loopbackWorks() ) {
			$this->logArranged( self::VIA_LOOPBACK, $misses );

			$this->requestLoopbackCollection( $this->loopbackMayBeQuick( $misses ) );

			return self::VIA_LOOPBACK;
		}

		if ( self::answerReachesATab() ) {
			self::$handoffToken = self::mintToken();

			$this->logArranged( self::VIA_BROWSER, $misses );
			$this->logSpawnEvent( 'pull_collect_browser_handoff', [ 'ttl' => self::TOKEN_TTL ] );

			return self::VIA_BROWSER;
		}

		$this->logArranged( self::VIA_INLINE, $misses );

		$this->collect();

		return self::VIA_INLINE;
	}

	private function logArranged( $via, $misses ) {
		$this->logSpawnEvent( 'pull_collect_arranged', [ 'via' => $via, 'misses' => $misses ] );
	}

	public static function handoffToken() {
		return self::$handoffToken;
	}

	private static function answerReachesATab() {
		return ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_doing_ajax();
	}

	private static function mintToken() {
		$token = wp_generate_password( 32, false, false );

		set_transient( self::TOKEN_TRANSIENT, wp_hash( $token ), self::TOKEN_TTL );

		return $token;
	}

	public function collect() {
		if ( Modes::SUSPECT === State::mode() ) {
			$this->suspectResolver->resolve();

			return;
		}


		$this->collector->run();
	}

	public function runAfterResponse() {
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			if ( function_exists( 'ignore_user_abort' ) ) {
				ignore_user_abort( true );
			}

			fastcgi_finish_request();
		}

		$this->collect();
	}

	private function logSpawnEvent( $id, array $data, $isError = false ) {
		JobLog::maybeInitRequest();
		JobLog::createNewGroup( JobLog::GROUP_ID_DOWNLOAD_JOBS, 'ATE pull delivery spawn' );

		if ( $isError ) {
			JobLog::addError( $id, $data );
		} else {
			JobLog::add( $id, $data );
		}

		JobLog::finishCurrentGroup();
	}

	private function loopbackWorks() {
		$known = State::getKey( 'loopback_ok' );

		if ( is_bool( $known ) ) {
			return $known;
		}

		$response = wp_remote_post(
			PullCollect::url(),
			[
				'timeout'   => 5,
				'blocking'  => true,
				'sslverify' => false,
				'body'      => [ 'token' => 'probe' ],
			]
		);

		$ok = self::probeReachedWpml( $response );

		State::recordLoopbackProbe( $ok );

		$this->logSpawnEvent( 'pull_collect_loopback_probe', [ 'loopback_ok' => $ok ] );

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

	private function loopbackMayBeQuick( $misses ) {
		if ( false === State::getKey( 'loopback_quick_ok' ) ) {
			return false;
		}

		if ( $misses > 0 ) {
			State::update( [ 'loopback_quick_ok' => false ] );
			$this->logSpawnEvent( 'pull_collect_loopback_quick_off', [ 'misses' => $misses ] );

			return false;
		}

		return true;
	}

	private function requestLoopbackCollection( $quick ) {
		$token = self::mintToken();

		if ( $quick ) {
			add_action( 'http_api_curl', [ $this, 'boundLoopbackRequest' ], PHP_INT_MAX, 3 );
		}

		try {
			wp_remote_post(
				PullCollect::url(),
				[
					'timeout'   => 1,
					'blocking'  => false,
					'sslverify' => false,
					'body'      => [ 'token' => $token ],
				]
			);
		} finally {
			if ( $quick ) {
				remove_action( 'http_api_curl', [ $this, 'boundLoopbackRequest' ], PHP_INT_MAX );
			}
		}
	}

	public function boundLoopbackRequest( $handle, $args, $url ) {
		if ( PullCollect::url() !== $url ) {
			return;
		}

		curl_setopt( $handle, CURLOPT_NOSIGNAL, true );
		curl_setopt( $handle, CURLOPT_TIMEOUT_MS, self::LOOPBACK_QUICK_MS );
	}

	public static function consumeToken( $token ) {
		$expected = get_transient( self::TOKEN_TRANSIENT );

		$matches = is_string( $expected )
			&& is_string( $token )
			&& '' !== $token
			&& hash_equals( $expected, wp_hash( $token ) );

		if ( $matches ) {
			delete_transient( self::TOKEN_TRANSIENT );
		}

		return $matches;
	}
}
