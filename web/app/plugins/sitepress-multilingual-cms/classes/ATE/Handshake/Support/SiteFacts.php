<?php

namespace WPML\TM\ATE\Handshake\Support;

use WPML\TM\ATE\Handshake\Clock;
use WPML\TM\ATE\Handshake\CronTick;
use WPML\TM\ATE\Handshake\Log;
use WPML\TM\ATE\Handshake\Marks;
use WPML\TM\ATE\Handshake\Plan;
use WPML\TM\ATE\Handshake\State;
use WPML\TM\ATE\PullDelivery\Cadence;
use WPML\TM\ATE\PullDelivery\State as PullState;
use WPML\TM\ATE\Sync\Process;
use WPML\TM\ATE\Sync\SyncChunk;

class SiteFacts {

	const EVENTS = 20;

	const EVENT_KINDS = [
		'wake_received',
		'wake_rejected',
		'drive_received',
		'run_start',
		'run_end',
		'declined',
		'closure_seen',
		'unknown_seen',
		'retry_sent',
		'acceptance_recorded',
		'acceptance_refused',
		'loopback_probe',
	];

	const CRON_OVERDUE_AFTER = 600;

	const CRON_SEARCH = 200;

	const RUNNING_SEARCH = 10;

	private $sources;

	public function __construct( array $sources = [] ) {
		$this->sources = array_merge( self::defaultSources(), $sources );
	}

	public function read() {
		$state   = (array) $this->call( 'state' );
		$state   = array_merge( State::defaults(), $state );
		$lastEnd = $this->lastEnd();

		return [
			'in_flight'       => max( 0, (int) $state['in_flight'] ),
			'in_flight_since' => (int) $state['in_flight'] > 0 ? (int) $state['sent_uncollected_since'] : 0,
			'last_run'        => $this->lastRun( $lastEnd ),
			'running'         => $this->running( $lastEnd ),
			'last_wake'       => (int) $state['last_wake_received_at'] > 0
				? [
					'at'   => (int) $state['last_wake_received_at'],
					'plan' => (string) $state['last_wake_plan'],
				]
				: null,
			'shape'           => $this->shape( $state ),
			'loopback'        => $this->loopback(),
			'cron'            => $this->cron( $state ),
			'acceptance'      => $this->acceptance( $state ),
			'marks'           => $this->marks(),
			'registration'    => $this->registration(),
			'events'          => $this->events(),
			'stalled'         => $this->stalled(),
			'parked'          => $this->parked(),
			'keys_refused'    => [
				'at'     => (int) $state['keys_refused_at'],
				'answer' => (string) $state['keys_refused_answer'],
			],
			'chunk'           => $this->chunk(),
		];
	}

	private function parked() {
		$lists = (array) $this->call( 'parked' );
		$count = 0;

		foreach ( [ 'insufficientBalanceJobIds', 'paymentPendingJobIds' ] as $list ) {
			$count += isset( $lists[ $list ] ) ? count( (array) $lists[ $list ] ) : 0;
		}

		return $count;
	}

	private function stalled() {
		$read = $this->call( 'stalled' );

		return is_array( $read ) && isset( $read['cause'] )
			? [
				'cause' => (string) $read['cause'],
				'count' => (int) $read['count'],
				'since' => (int) $read['since'],
				'door'  => isset( $read['door'] ) ? (string) $read['door'] : '',
			]
			: [ 'cause' => Stalled::NONE, 'count' => 0, 'since' => 0, 'door' => '' ];
	}

	private function lastRun( array $end ) {
		if ( ! isset( $end['t'] ) || (int) $end['t'] < 1 ) {
			return null;
		}

		$data = isset( $end['data'] ) && is_array( $end['data'] ) ? $end['data'] : [];

		return [
			'at'      => (int) $end['t'],
			'trigger' => isset( $data['trigger'] ) ? (string) $data['trigger'] : '',
			'applied' => isset( $data['written'] ) ? (int) $data['written'] : null,
		];
	}

	private function lastEnd() {
		$ends = $this->log( 1, [ 'run_end' ] );

		return $ends && is_array( $ends[0] ) ? $ends[0] : [];
	}

	private function running( array $end ) {
		$start = [];

		foreach ( $this->log( self::RUNNING_SEARCH, [ 'run_start' ] ) as $row ) {
			$data = isset( $row['data'] ) && is_array( $row['data'] ) ? $row['data'] : [];

			if ( empty( $data['lock_busy'] ) && ! isset( $data['error'] ) ) {
				$start = $row;
				break;
			}
		}

		if ( ! isset( $start['t'] ) || (int) $start['t'] < 1 ) {
			return null;
		}

		if ( isset( $end['t'] ) ) {
			$after = isset( $start['id'], $end['id'] ) ? (int) $start['id'] > (int) $end['id'] : (int) $start['t'] > (int) $end['t'];

			if ( ! $after ) {
				return null;
			}
		}

		$now    = (int) $this->call( 'clock' );
		$budget = max( 1, (int) $this->call( 'budget' ) );

		if ( $now < 1 || $now - (int) $start['t'] > $budget ) {
			return null;
		}

		return [
			'at'      => (int) $start['t'],
			'trigger' => isset( $start['data']['trigger'] ) ? (string) $start['data']['trigger'] : '',
		];
	}

	private function shape( array $state ) {
		if ( (int) $state['last_wake_received_at'] < 1 ) {
			return 'island';
		}

		if ( $this->call( 'finishesEarly' ) ) {
			return Plan::BACKGROUND;
		}

		$loopback = $this->call( 'loopback' );

		if ( is_bool( $loopback ) ) {
			return $loopback ? Plan::SPAWNED : Plan::HELP;
		}

		$plan = (string) $state['last_wake_plan'];

		return in_array( $plan, [ Plan::BACKGROUND, Plan::SPAWNED, Plan::HELP ], true ) ? $plan : '';
	}

	private function loopback() {
		$verdict = $this->call( 'loopback' );
		$probes  = $this->log( 1, [ 'loopback_probe' ] );

		return [
			'ok' => is_bool( $verdict ) ? $verdict : null,
			'at' => $probes ? (int) $probes[0]['t'] : 0,
		];
	}

	private function cron( array $state ) {
		$next    = (int) $this->call( 'cronNext' );
		$lastRan = 0;

		foreach ( $this->log( self::CRON_SEARCH, [ 'run_start' ] ) as $row ) {
			if ( isset( $row['data']['trigger'] ) && 'cron' === $row['data']['trigger'] ) {
				$lastRan = (int) $row['t'];
				break;
			}
		}

		return [
			'disabled' => (bool) $this->call( 'cronDisabled' ),
			'next'     => $next,
			'overdue'  => $next > 0 && $next < (int) $this->call( 'now' ) - self::CRON_OVERDUE_AFTER,
			'last_ran' => $lastRan,
			'interval' => CronTick::interval( $state ),
		];
	}

	private function acceptance( array $state ) {
		return [
			'accepted_at'    => (int) $state['handshake_accepted_at'],
			'refused_at'     => (int) $state['handshake_refused_at'],
			'refused_status' => (int) $state['handshake_refused_status'],
			'refused_route'  => (string) $state['handshake_refused_route'],
		];
	}

	private function marks() {
		$byMark = [];
		$ids    = [];

		foreach ( [ Marks::FAILED_ON_SITE, Marks::NEEDS_RESEND, Marks::DECLINED, Marks::NOT_NEEDED ] as $mark ) {
			$byMark[ $mark ] = (array) $this->call( 'marks', $mark );

			foreach ( $byMark[ $mark ] as $job ) {
				$ids[] = (int) $job['job_id'];
			}
		}

		$jobs = $ids ? (array) $this->call( 'jobs', array_values( array_unique( $ids ) ) ) : [];
		$out  = [];

		foreach ( $byMark as $mark => $marked ) {
			$out[ $mark ] = [];

			foreach ( $marked as $job ) {
				$jobId = (int) $job['job_id'];
				$known = isset( $jobs[ $jobId ] ) ? (array) $jobs[ $jobId ] : [];
				$type  = isset( $known['error_type'] ) ? (string) $known['error_type'] : '';
				$msg   = isset( $known['error_message'] ) ? (string) $known['error_message'] : '';

				$out[ $mark ][] = [
					'job_id'     => $jobId,
					'ate_job_id' => (int) $job['ate_job_id'],
					'title'      => isset( $known['title'] ) ? (string) $known['title'] : '',
					'language'   => isset( $known['language'] ) ? (string) $known['language'] : '',
					'words'      => isset( $known['words'] ) && null !== $known['words'] ? (int) $known['words'] : null,
					'reason'     => Marks::FAILED_ON_SITE === $mark ? self::reasonKind( $type, $msg ) : '',
					'details'    => '' === $msg ? '' : trim( $type . ': ' . $msg, ': ' ),
				];
			}
		}

		return $out;
	}

	public static function reasonKind( $type, $message ) {
		if ( '' === $type && '' === $message ) {
			return '';
		}

		$lower = strtolower( $message );

		if ( false !== strpos( $lower, 'too long' ) ) {
			return 'too_long';
		}

		if ( false !== strpos( $lower, 'fatal' ) || false !== strpos( $lower, 'php ' ) || false !== strpos( $lower, 'uncaught' ) ) {
			return 'php_error';
		}

		if ( false !== strpos( $lower, 'database' ) || false !== strpos( $lower, 'sql' ) ) {
			return 'database';
		}

		$byType = [
			'ApplyError'    => 'save',
			'DownloadError' => 'download',
			'SyncError'     => 'sync',
		];

		return isset( $byType[ $type ] ) ? $byType[ $type ] : '';
	}

	private function registration() {
		$read       = (array) $this->call( 'registration' );
		$registered = isset( $read['registered'] ) ? (string) $read['registered'] : '';
		$current    = isset( $read['current'] ) ? (string) $read['current'] : '';

		return [
			'registered' => $registered,
			'current'    => $current,
			'matches'    => '' === $registered || '' === $current ? null : self::sameSite( $registered, $current ),
		];
	}

	public static function sameSite( $a, $b ) {
		$normal = function ( $url ) {
			$url = strtolower( trim( (string) $url ) );
			$url = (string) preg_replace( '#^[a-z]+://#', '', $url );
			$url = (string) preg_replace( '#^www\.#', '', $url );

			return rtrim( $url, '/' );
		};

		return $normal( $a ) === $normal( $b );
	}

	private function events() {
		$out = [];

		foreach ( $this->log( self::EVENTS, self::EVENT_KINDS ) as $row ) {
			$out[] = [
				't'    => (int) $row['t'],
				'kind' => (string) $row['kind'],
				'data' => (array) $row['data'],
			];
		}

		return $out;
	}

	private function log( $limit, array $kinds ) {
		return (array) $this->call( 'log', $limit, $kinds );
	}

	private function call( $source, ...$args ) {
		try {
			return call_user_func_array( $this->sources[ $source ], $args );
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	private static function defaultSources() {
		return [
			'state'         => [ State::class, 'get' ],
			'loopback'      => function () {
				return PullState::getKey( 'loopback_ok' );
			},
			'log'           => [ Log::class, 'read' ],
			'marks'         => function ( $mark ) {
				return ( new Marks() )->jobsMarked( $mark );
			},
			'jobs'          => [ JobFacts::class, 'of' ],
			'cronNext'      => function () {
				return wp_next_scheduled( CronTick::HOOK );
			},
			'cronDisabled'  => function () {
				return defined( 'DISABLE_WP_CRON' ) && constant( 'DISABLE_WP_CRON' );
			},
			'finishesEarly' => [ Plan::class, 'phpFinishesEarly' ],
			'registration'  => [ JobFacts::class, 'registration' ],
			'now'           => 'time',
			'clock'         => [ Clock::class, 'now' ],
			'budget'        => [ Cadence::class, 'collectBudgetSeconds' ],
			'stalled'       => function () {
				return ( new Stalled() )->read();
			},
			'parked'        => [ Stalled::class, 'parkedLists' ],
			'chunk'         => function () {
				return [
					'value'      => SyncChunk::size(),
					'default'    => SyncChunk::defaultSize(),
					'is_default' => SyncChunk::isDefault(),
				];
			},
		];
	}

	private function chunk() {
		$chunk = $this->call( 'chunk' );

		if ( ! is_array( $chunk ) || ! isset( $chunk['value'] ) ) {
			return [
				'value'      => Process::SYNC_ALL_CHUNK,
				'default'    => Process::SYNC_ALL_CHUNK,
				'is_default' => true,
			];
		}

		return [
			'value'      => (int) $chunk['value'],
			'default'    => isset( $chunk['default'] ) ? (int) $chunk['default'] : (int) $chunk['value'],
			'is_default' => ! empty( $chunk['is_default'] ),
		];
	}
}
