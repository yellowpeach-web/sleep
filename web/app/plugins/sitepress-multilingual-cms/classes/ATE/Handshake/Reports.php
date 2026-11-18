<?php

namespace WPML\TM\ATE\Handshake;

use WPML\TM\ATE\ClonedSites\ReconnectState;

class Reports {

	const OPTION          = 'wpml_ate_handshake_reports';
	const STATE_OPTION    = 'wpml_ate_handshake_reported_state';
	const IN_FLIGHT_OPTION = 'wpml_ate_handshake_in_flight_ids';

	const RUN_SUMMARY  = 'run_summary';
	const JOB_RELEASED = 'job_released';
	const CREATE_ACK   = 'create_ack';
	const STATE        = 'state';
	const UNDECLARE    = 'undeclare';

	const RELEASED_SUPERSEDED            = 'superseded';
	const RELEASED_CANCELLED_UNCONFIRMED = 'cancelled_unconfirmed';
	const RELEASED_TRASHED               = 'trashed';
	const RELEASED_WRITTEN_UNCONFIRMED   = 'written_unconfirmed';
	const RELEASED_SYNC_ERROR            = 'sync_error';
	const RELEASED_LOST_ATE_ID           = 'lost_ate_id';
	const RELEASED_MANUAL_HELD           = 'manual_held';
	const RELEASED_OTHER = 'other';

	const UNDECLARE_DEACTIVATED = 'deactivated';
	const UNDECLARE_DOWNGRADED  = 'downgraded';

	const BATCH = 100;

	const CALLS_PER_FLUSH = 3;

	const QUEUE_CAP = 1000;

	const MAX_ATTEMPTS = 2;

	const RELEASED_PER_RUN = 200;

	const IN_FLIGHT_CAP = 10000;

	const CRON_OVERDUE_SECONDS = 900;

	private static $shutdownFlush = false;

	private static $inRun = false;

	private static $testSender = null;

	private static $testReleasedReader = null;

	public static $testState = null;

	public static function add( $kind, array $data ) {
		self::addAll( [ [ $kind, $data ] ] );
	}

	public static function addAll( array $reports ) {
		if ( ! $reports ) {
			return;
		}

		try {
			$queue = self::queue();
			foreach ( $reports as $report ) {
				$queue[] = [
					'kind'         => (string) $report[0],
					'at'           => gmdate( 'Y-m-d\TH:i:s\Z', time() ),
					'wpml_version' => self::wpmlVersion(),
					'data'         => (array) $report[1],
					'attempts'     => 0,
				];
			}

			if ( count( $queue ) > self::QUEUE_CAP ) {
				$queue = array_slice( $queue, -self::QUEUE_CAP );
			}

			self::writeQueue( $queue );

			if ( ! self::$inRun ) {
				self::flushAtShutdown();
			}
		} catch ( \Throwable $e ) {
			return;
		}
	}

	public static function setInRun( $inRun ) {
		self::$inRun = (bool) $inRun;
	}

	public static function flush( ?AteClient $ate = null ) {
		try {
			return self::send( $ate );
		} catch ( \Throwable $e ) {
			return 0;
		}
	}

	private static function send( ?AteClient $ate ) {
		$queue = self::queue();

		if ( ! $queue || ReconnectState::get() ) {
			return 0;
		}

		$ate   = $ate ?: new AteClient();
		$taken = 0;
		$kept  = [];

		foreach ( array_chunk( $queue, self::BATCH ) as $index => $batch ) {
			if ( $index >= self::CALLS_PER_FLUSH ) {
				$kept = array_merge( $kept, $batch );
				continue;
			}

			$reports = array_map(
				function ( array $report ) {
					return array_diff_key( $report, [ 'attempts' => true ] );
				},
				$batch
			);

			$answer = self::$testSender ? call_user_func( self::$testSender, $reports ) : $ate->report( $reports );
			$answer = is_array( $answer ) ? $answer : [ 'code' => (int) $answer, 'body' => null ];
			$code   = isset( $answer['code'] ) ? (int) $answer['code'] : 0;

			if ( $code >= 200 && $code < 300 ) {
				$taken += self::storedOf( isset( $answer['body'] ) ? $answer['body'] : null, count( $batch ) );
				continue;
			}

			foreach ( $batch as $report ) {
				$report['attempts'] = (int) $report['attempts'] + 1;

				if ( $report['attempts'] < self::MAX_ATTEMPTS ) {
					$kept[] = $report;
				}
			}
		}

		$added = array_slice( self::queue(), count( $queue ) );
		self::writeQueue( array_merge( $kept, $added ) );

		return $taken;
	}

	private static function storedOf( $body, $sent ) {
		if ( is_array( $body ) && isset( $body['stored'] ) && is_numeric( $body['stored'] ) ) {
			return max( 0, min( (int) $sent, (int) $body['stored'] ) );
		}

		return (int) $sent;
	}

	private static function flushAtShutdown() {
		if ( self::$shutdownFlush || ! function_exists( 'add_action' ) ) {
			return;
		}

		self::$shutdownFlush = true;

		add_action(
			'shutdown',
			function () {
				if ( function_exists( 'fastcgi_finish_request' ) ) {
					fastcgi_finish_request();
				}

				self::flush();
			},
			PHP_INT_MAX
		);
	}

	public static function runSummary( $trigger, array $counts, $elapsedMs ) {
		$get = function ( $key ) use ( $counts ) {
			return isset( $counts[ $key ] ) ? (int) $counts[ $key ] : 0;
		};

		return [
			'trigger'    => self::trigger( $trigger ),
			'listed'     => $get( 'listed' ),
			'named'      => $get( 'named' ),
			'fetched'    => $get( 'fetched' ),
			'applied'    => $get( 'written' ),
			'confirmed'  => $get( 'confirmed' ),
			'declined'   => $get( 'declined' ),
			'budget_hit' => ! empty( $counts['budget_hit'] ),
			'work_left'  => ! empty( $counts['work_left'] ),
			'errors'     => isset( $counts['errors'] ) && is_array( $counts['errors'] ) ? array_map( 'intval', $counts['errors'] ) : [],
			'elapsed_ms' => max( 0, (int) $elapsedMs ),
		];
	}

	public static function trigger( $trigger ) {
		$map = [
			Runner::TRIGGER_WAKE_BACKGROUND => 'wake',
			Runner::TRIGGER_WAKE_SPAWNED    => 'loopback',
		];

		return isset( $map[ $trigger ] ) ? $map[ $trigger ] : (string) $trigger;
	}

	public static function trackInFlight( array $inFlight, array $decided, $trigger ) {
		$inFlight  = array_values( array_unique( array_map( 'intval', $inFlight ) ) );
		$decided   = array_map( 'intval', $decided );
		$stored    = get_option( self::IN_FLIGHT_OPTION, null );
		$known     = is_array( $stored ) && isset( $stored['ids'] ) ? array_map( 'intval', (array) $stored['ids'] ) : null;
		$wasHeld   = is_array( $stored ) && isset( $stored['held'] ) ? array_map( 'intval', (array) $stored['held'] ) : [];
		$stillHeld = array_values( array_diff( $wasHeld, $inFlight, $decided ) );

		$released = null !== $known ? array_values( array_diff( $known, $inFlight, $decided, $wasHeld ) ) : [];
		$reported = array_slice( $released, 0, self::RELEASED_PER_RUN );
		$held     = $stillHeld;

		$rows    = self::$testReleasedReader ? call_user_func( self::$testReleasedReader, $reported ) : self::readReleased( $reported );
		$reports = [];

		foreach ( $rows as $ateId => $row ) {
			$reason = self::releasedReason( $row );

			if ( self::RELEASED_MANUAL_HELD === $reason ) {
				$held[] = (int) $ateId;
			}

			$reports[] = [
				self::JOB_RELEASED,
				[
					'ate_id'       => (int) $ateId,
					'id'           => $row ? (int) $row->rid : 0,
					'local_status' => $row && null !== $row->status ? (int) $row->status : null,
					'reason'       => $reason,
					'trigger'      => self::trigger( $trigger ),
				],
			];
		}

		self::addAll( $reports );

		$remember = [
			'ids'  => array_slice( array_values( array_diff( $inFlight, $decided ) ), 0, self::IN_FLIGHT_CAP ),
			'held' => array_slice( array_values( array_unique( $held ) ), 0, self::IN_FLIGHT_CAP ),
		];

		if ( $stored !== $remember ) {
			update_option( self::IN_FLIGHT_OPTION, $remember, false );
		}

		return max( 0, count( $released ) - count( $reported ) );
	}

	public static function releasedReason( $row ) {
		if ( ! $row ) {
			return self::RELEASED_LOST_ATE_ID;
		}

		$status = null === $row->status ? null : (int) $row->status;

		if ( null === $status || (int) $row->newest_job_id > (int) $row->job_id ) {
			return self::RELEASED_SUPERSEDED;
		}

		if ( ! (int) $row->has_original ) {
			return self::RELEASED_TRASHED;
		}

		if ( self::errorOverLimit( $row ) || ICL_TM_ATE_UNSOLVABLE === $status ) {
			return self::RELEASED_SYNC_ERROR;
		}

		if ( (int) $row->translated && ICL_TM_COMPLETE === $status ) {
			return self::RELEASED_WRITTEN_UNCONFIRMED;
		}

		if ( in_array( $status, [ ICL_TM_NOT_TRANSLATED, ICL_TM_ATE_CANCELLED, ICL_TM_COMPLETE, ICL_TM_DUPLICATE ], true ) ) {
			return self::RELEASED_CANCELLED_UNCONFIRMED;
		}

		if ( ! (int) $row->automatic && (int) $row->ate_sync_count >= \WPML\TM\ATE\Jobs::LONGSTANDING_AT_ATE_SYNC_COUNT ) {
			return self::RELEASED_MANUAL_HELD;
		}

		if ( ICL_TM_ATE_NEEDS_RETRY === $status ) {
			return self::RELEASED_LOST_ATE_ID;
		}

		return self::RELEASED_OTHER;
	}

	private static function errorOverLimit( $row ) {
		$limits = \WPML\Core\Component\Translation\Domain\Entity\JobError::RETRY_LIMITS;
		$type   = isset( $row->error_type ) ? (string) $row->error_type : '';

		return '' !== $type && isset( $limits[ $type ] ) && (int) $row->error_counter >= $limits[ $type ];
	}

	private static function readReleased( array $ateIds ) {
		global $wpdb;

		if ( ! $ateIds ) {
			return [];
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT j.editor_job_id AS ate_id, j.job_id, j.rid, j.automatic, j.translated, j.ate_sync_count, ts.status,
				        ( SELECT MAX( newer.job_id ) FROM {$wpdb->prefix}icl_translate_job newer WHERE newer.rid = j.rid ) AS newest_job_id,
				        ( SELECT original.translation_id
				            FROM {$wpdb->prefix}icl_translations target
				           INNER JOIN {$wpdb->prefix}icl_translations original
				                   ON original.trid = target.trid
				                  AND original.element_type = target.element_type
				                  AND original.source_language_code IS NULL
				                  AND original.element_id IS NOT NULL
				           WHERE target.translation_id = ts.translation_id
				           LIMIT 1 ) IS NOT NULL AS has_original,
				        e.error_type, e.counter AS error_counter
				   FROM {$wpdb->prefix}icl_translate_job j
				   LEFT JOIN {$wpdb->prefix}icl_translation_status ts ON ts.rid = j.rid
				   LEFT JOIN {$wpdb->prefix}icl_translate_unsolvable_jobs e ON e.job_id = j.job_id
				  WHERE j.editor_job_id IN (" . implode( ', ', array_fill( 0, count( $ateIds ), '%d' ) ) . ')
				  ORDER BY j.job_id ASC',
				$ateIds
			)
		);

		$byAteId = array_fill_keys( $ateIds, null );

		foreach ( (array) $rows as $row ) {
			$byAteId[ (int) $row->ate_id ] = $row;
		}

		return $byAteId;
	}

	public static function queueStateIfChanged() {
		$state = self::$testState ?: self::stateSnapshot();
		$hash  = md5( (string) wp_json_encode( $state ) );

		if ( get_option( self::STATE_OPTION, '' ) === $hash ) {
			return false;
		}

		update_option( self::STATE_OPTION, $hash, false );
		self::add( self::STATE, $state );

		return true;
	}

	public static function stateSnapshot() {
		$next = function_exists( 'wp_next_scheduled' ) ? wp_next_scheduled( CronTick::HOOK ) : false;

		return [
			'wpml_version'         => self::wpmlVersion(),
			'translate_everything' => (bool) \WPML\Setup\Option::shouldTranslateEverything(),
			'translation_mode'     => self::translationModeName( \WPML\Setup\Option::getTranslationMode() ),
			'editor'               => (string) wpml_get_tm_sub_setting( 'doc_translation_method', '' ),
			'chunk'                => Runner::chunkSize(),
			'cron_ok'              => ! ( $next && (int) $next < time() - self::CRON_OVERDUE_SECONDS ),
		];
	}

	public static function translationModeName( $mode ) {
		if ( is_array( $mode ) ) {
			return implode( ',', array_map( 'strval', array_filter( $mode, 'is_scalar' ) ) );
		}

		return is_scalar( $mode ) ? (string) $mode : '';
	}

	private static function wpmlVersion() {
		return defined( 'ICL_SITEPRESS_VERSION' ) ? (string) ICL_SITEPRESS_VERSION : '';
	}

	public static function queue() {
		$queue = get_option( self::OPTION, [] );

		return is_array( $queue ) ? array_values( $queue ) : [];
	}

	private static function writeQueue( array $queue ) {
		if ( $queue ) {
			update_option( self::OPTION, array_values( $queue ), false );
		} else {
			delete_option( self::OPTION );
		}
	}

	public static function sendWithForTests( $sender ) {
		self::$testSender = $sender;
	}

	public static function readReleasedWithForTests( $reader ) {
		self::$testReleasedReader = $reader;
	}

	public static function resetForTests() {
		self::$testSender         = null;
		self::$testReleasedReader = null;
		self::$testState          = null;
		self::$shutdownFlush = false;
		self::$inRun         = false;
	}
}
