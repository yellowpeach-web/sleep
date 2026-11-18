<?php

namespace WPML\TM\ATE\Handshake\Support;

use WPML\TM\ATE\Handshake\AteClient;
use WPML\TM\ATE\Handshake\Declaration;
use WPML\TM\ATE\Handshake\Log;
use WPML\TM\ATE\Handshake\Marks;
use WPML\TM\ATE\Handshake\Plan;
use WPML\TM\ATE\Handshake\Routes;
use WPML\TM\ATE\Handshake\Runner;
use WPML\TM\ATE\PullDelivery\State as PullState;

class Check {

	const ALL_GOOD           = 'all_good';
	const FAILED             = 'failed_on_site';
	const UNREACHABLE        = 'unreachable';
	const BLOCKED            = 'blocked';
	const SIGNATURE          = 'signature';
	const HEARS_BUT_SILENT   = 'hears_but_silent';
	const NO_HANDSHAKE       = 'no_handshake';
	const NO_RECORD          = 'no_record';
	const SYSTEM_UNREACHABLE = 'system_unreachable';
	const THROTTLED          = 'throttled';
	const KEYS_REFUSED       = 'keys_refused';
	const SITE_MOVED         = 'site_moved';

	const RECOMMEND_NONE       = '';
	const RECOMMEND_RETRY      = 'retry';
	const RECOMMEND_CHECK      = 'check';
	const RECOMMEND_REREGISTER = 'reregister';
	const RECOMMEND_COLLECT    = 'collect';
	const RECOMMEND_REPORT     = 'report';
	const RECOMMEND_REGISTER   = 'register';

	private $ate;

	private $collect;

	private $loopback;

	private $facts;

	private $wakePath;

	public function __construct( ?AteClient $ate = null, $collect = null, $loopback = null, ?SiteFacts $facts = null, $wakePath = null ) {
		$this->ate      = $ate ?: new AteClient();
		$this->collect  = $collect ?: [ self::class, 'collectNow' ];
		$this->loopback = $loopback ?: [ self::class, 'probeLoopback' ];
		$this->facts    = $facts ?: new SiteFacts();
		$this->wakePath = $wakePath ?: [ self::class, 'siteWakePath' ];
	}

	public function run() {
		$before  = self::before( $this->facts->read() );
		$collect = self::collectCounts( call_user_func( $this->collect ) );
		$probe   = $this->probe();
		$loop    = (bool) call_user_func( $this->loopback );
		$facts   = $this->facts->read();

		$self = [
			'loopback'      => $loop,
			'cron_disabled' => (bool) $facts['cron']['disabled'],
			'cron_overdue'  => (bool) $facts['cron']['overdue'],
			'url_matches'   => $facts['registration']['matches'],
			'registered'    => (string) $facts['registration']['registered'],
			'current'       => (string) $facts['registration']['current'],
		];

		$failed = isset( $facts['marks'][ Marks::FAILED_ON_SITE ] ) ? $facts['marks'][ Marks::FAILED_ON_SITE ] : [];
		$card   = self::classify( $probe, $failed, $before, $self );

		return [
			'card'        => $card['card'],
			'recommended' => $card['recommended'],
			'reason'      => $card['reason'],
			'throttled'   => 'throttled' === $probe['class'],
			'collect'     => $collect,
			'probe'       => $probe,
			'self'        => $self,
			'failed_jobs' => $failed,
		];
	}

	public static function before( array $facts ) {
		$wake = isset( $facts['last_wake'] ) && is_array( $facts['last_wake'] ) ? $facts['last_wake'] : [];
		$run  = isset( $facts['last_run'] ) && is_array( $facts['last_run'] ) ? $facts['last_run'] : [];

		return [
			'last_wake_received_at' => isset( $wake['at'] ) ? (int) $wake['at'] : 0,
			'last_wake_plan'        => isset( $wake['plan'] ) ? (string) $wake['plan'] : '',
			'last_run_at'           => isset( $run['at'] ) ? (int) $run['at'] : 0,
			'in_flight'             => isset( $facts['in_flight'] ) ? (int) $facts['in_flight'] : 0,
			'running'               => ! empty( $facts['running'] ),
		];
	}

	public static function classify( array $probe, array $failed, array $before, array $self ) {
		$card = function ( $card, $recommended, $reason = '' ) {
			return [
				'card'        => $card,
				'recommended' => $recommended,
				'reason'      => $reason,
			];
		};

		switch ( $probe['class'] ) {
			case 'unreachable':
				return $card( self::UNREACHABLE, self::RECOMMEND_CHECK );
			case 'impostor':
				return $card( self::BLOCKED, self::RECOMMEND_CHECK );
			case 'signature_rejected':
				return $card( self::SIGNATURE, self::RECOMMEND_REREGISTER );
			case 'no_handshake':
				return $card( self::NO_HANDSHAKE, self::RECOMMEND_COLLECT );
			case self::KEYS_REFUSED:
				return $card( self::KEYS_REFUSED, self::RECOMMEND_REGISTER );
			case self::SITE_MOVED:
				return $card( self::SITE_MOVED, self::RECOMMEND_REGISTER );
		}

		if ( $failed ) {
			return $card( self::FAILED, self::RECOMMEND_RETRY );
		}

		$silent = self::silentReason( $before, $self );

		if ( '' !== $silent ) {
			return $card( self::HEARS_BUT_SILENT, self::RECOMMEND_COLLECT, $silent );
		}

		if ( 'throttled' === $probe['class'] ) {
			return $card( self::THROTTLED, self::RECOMMEND_NONE );
		}

		if ( 'system_unreachable' === $probe['class'] ) {
			return $card( self::SYSTEM_UNREACHABLE, self::RECOMMEND_REPORT );
		}

		if ( 'no_record' === $probe['class'] ) {
			return $card( self::NO_RECORD, self::RECOMMEND_NONE );
		}

		return $card( self::ALL_GOOD, self::RECOMMEND_NONE );
	}

	public static function silentReason( array $before, array $self ) {
		$wake = isset( $before['last_wake_received_at'] ) ? (int) $before['last_wake_received_at'] : 0;
		$run  = isset( $before['last_run_at'] ) ? (int) $before['last_run_at'] : 0;
		$in   = isset( $before['in_flight'] ) ? (int) $before['in_flight'] : 0;

		if ( $wake < 1 || $in < 1 || $run >= $wake || ! empty( $before['running'] ) ) {
			return '';
		}

		$plan = isset( $before['last_wake_plan'] ) ? (string) $before['last_wake_plan'] : '';

		if ( Plan::SPAWNED === $plan && false === $self['loopback'] ) {
			return 'loopback';
		}

		if ( ! empty( $self['cron_overdue'] ) ) {
			return 'cron';
		}

		return 'host';
	}

	private function probe() {
		Declaration::begin();

		try {
			$answer = $this->ate->probe();
		} finally {
			Declaration::end();
		}

		return self::readProbe( $answer, (string) call_user_func( $this->wakePath ) );
	}

	public static function readProbe( array $answer, $sitePath ) {
		$code = isset( $answer['code'] ) ? (int) $answer['code'] : 0;
		$body = isset( $answer['body'] ) && is_array( $answer['body'] ) ? $answer['body'] : [];
		$read = [
			'answered' => false,
			'class'    => 'system_unreachable',
			'outcome'  => '',
			'status'   => null,
			'path'     => $sitePath,
			'what'     => '',
			'at'       => '',
		];

		if ( 429 === $code ) {
			$read['class'] = 'throttled';

			return $read;
		}

		if ( 404 === $code && isset( $body['error'] ) && 'no_handshake' === $body['error'] ) {
			$read['class'] = 'no_record';

			return $read;
		}

		if ( 403 === $code ) {
			$read['class']  = self::KEYS_REFUSED;
			$read['status'] = 403;

			return $read;
		}

		if ( 426 === $code ) {
			$read['class']  = self::SITE_MOVED;
			$read['status'] = 426;

			return $read;
		}

		if ( $code < 200 || $code >= 300 || ! isset( $body['class'] ) ) {
			return $read;
		}

		$status = isset( $body['status'] ) && is_numeric( $body['status'] ) ? (int) $body['status'] : null;

		return [
			'answered' => true,
			'class'    => (string) $body['class'],
			'outcome'  => isset( $body['outcome'] ) ? (string) $body['outcome'] : '',
			'status'   => $status,
			'path'     => isset( $body['path'] ) && '' !== (string) $body['path'] ? (string) $body['path'] : $sitePath,
			'what'     => self::whatAnswered( $status, isset( $body['body_head'] ) ? (string) $body['body_head'] : '' ),
			'at'       => isset( $body['at'] ) ? (string) $body['at'] : '',
		];
	}

	public static function whatAnswered( $status, $bodyHead ) {
		if ( null !== $status && $status >= 300 && $status < 400 ) {
			return 'redirect';
		}

		$head = strtolower( $bodyHead );

		foreach ( [ 'challenge', 'captcha', 'just a moment', 'attention required' ] as $marker ) {
			if ( false !== strpos( $head, $marker ) ) {
				return 'challenge';
			}
		}

		return 'page';
	}

	public static function collectCounts( $counts ) {
		if ( ! is_array( $counts ) ) {
			return [
				'ran'     => false,
				'busy'    => false,
				'applied' => 0,
				'failed'  => 0,
			];
		}

		$written = isset( $counts['written'] ) ? (int) $counts['written'] : 0;
		$fetched = isset( $counts['fetched'] ) ? (int) $counts['fetched'] : 0;

		return [
			'ran'     => true,
			'busy'    => ! empty( $counts['lock_busy'] ),
			'applied' => $written,
			'failed'  => max( 0, $fetched - $written ),
		];
	}

	public static function collectNow() {
		return ( new Runner() )->run( Runner::TRIGGER_MANUAL );
	}

	public static function probeLoopback() {
		PullState::update( [ 'loopback_ok' => null ] );

		$ok = Plan::loopbackVerdict();

		Log::write( 'loopback_probe', [ 'ok' => $ok ] );

		return $ok;
	}

	public static function siteWakePath() {
		return (string) wp_parse_url( Routes::url( Routes::WAKE ), PHP_URL_PATH );
	}
}
