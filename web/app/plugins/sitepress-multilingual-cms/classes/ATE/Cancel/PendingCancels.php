<?php

namespace WPML\TM\ATE\Cancel;

use WPML\Core\Component\ATE\Domain\SiteRegistration;
use WPML\Core\Component\Translation\Application\Service\AutomaticJobsCancellation\AteResponse;
use WPML\TM\ATE\ClonedSites\ApiCommunication;
use WPML\TM\ATE\Handshake\Decline;
use WPML\TM\Jobs\JobLog;
use WPML\Utilities\AdvisoryLockFactory;
use WPML\Utilities\Lock;
use function WPML\Container\make;

class PendingCancels {

	const OPTION = 'wpml_tm_ate_pending_cancels';

	const CAP = 200;

	const ROW_SIZE = 2000;

	const ROW_OPTION_PREFIX = 'wpml_tm_ate_pending_cancels_row_';

	const ADMIN_RETRY_INTERVAL = 60;

	const REPLAY_TIMEOUT = 10;

	const REPLAY_CHUNK = 50;

	const RUN_SOURCES = [ CancelSource::TRANSLATE_EVERYTHING_OFF ];

	const OWED_FOR_RUN_ACTION = 'wpml_tm_ate_cancels_owed_for_run';

	const OWED_CANCEL_ANSWERED_ACTION = 'wpml_tm_ate_owed_cancel_answered';

	const RUN_REPLAY_NOTHING_OWED  = 'nothing_owed';
	const RUN_REPLAY_OWED_HERE     = 'owed_in_this_request';
	const RUN_REPLAY_NOT_DUE       = 'not_due';
	const RUN_REPLAY_UNREACHABLE   = 'unreachable_window';
	const RUN_REPLAY_NO_API_CLIENT = 'no_api_client';
	const RUN_REPLAY_BUSY          = 'another_run_replays';

	const REPLAY_LOCK         = 'wpml_tm_ate_owed_replay';
	const REPLAY_LOCK_TIMEOUT = 60;

	const WRITE_LOCK_TIMEOUT = 10;

	private static $inPass = false;

	private static $owedInThisRequest = false;

	const MAX_AGE = 2592000;

	const NOT_DIALLED_CODES = [ SiteRegistration::NOT_REGISTERED_ERROR_CODE, ApiCommunication::RECONNECTING_ERROR_CODE ];

	const ENDPOINT_CANCEL = 'cancel';

	const ENDPOINT_HIDE = 'hide';

	const ENDPOINT_DECLINE = 'decline';

	const ENDPOINT_APPLY_FIRST = 'apply_first';

	const ATE_STATUSES_STILL_AT_WORK = [
		\WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_CREATED,
		\WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_TRANSLATING,
		\WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_NOT_ENOUGH,
		\WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_WAITING_AUTOMATIC_PAYMENT,
	];

	const ATE_STATUSES_COMPLETED = [
		\WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_TRANSLATED,
		\WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_DELIVERING,
	];

	const BACKGROUND_LABEL = 'wpml-ate-cancel-replay';

	public static function isUnanswered( $response ) {
		if ( is_wp_error( $response ) ) {
			return \WPML_TM_ATE_API::isTransportFailure( $response )
				|| in_array( $response->get_error_code(), self::NOT_DIALLED_CODES, true );
		}

		return ! is_array( $response ) && ! is_object( $response );
	}

	public static function isOwed( $response ) {
		return self::isUnanswered( $response ) || \WPML_TM_ATE_API::isGatewayErrorAnswer( $response );
	}

	public static function firstTryRequestArgs() {
		return [ \WPML_TM_ATE_API::RAW_GATEWAY_ERROR_ARG => true ];
	}

	private static function wasDialled( $response ) {
		return ! ( is_wp_error( $response ) && in_array( $response->get_error_code(), self::NOT_DIALLED_CODES, true ) );
	}

	private static function stopsThePass( $response ) {
		return ! is_wp_error( $response )
			|| ApiCommunication::RECONNECTING_ERROR_CODE !== $response->get_error_code();
	}

	public static function remember( array $ateJobIds, $endpoint, $reason, $force = false, array $context = [], $firstTry = null ) {
		$byAteJobId = [];
		foreach ( self::ids( $ateJobIds ) as $ateJobId ) {
			$byAteJobId[ $ateJobId ] = $context;
		}

		self::rememberEach( $byAteJobId, $endpoint, $reason, $force, $firstTry );
	}

	public static function rememberEach( array $byAteJobId, $endpoint, $reason, $force = false, $firstTry = null ) {
		$entries = [];
		foreach ( $byAteJobId as $ateJobId => $context ) {
			$ateJobId = (int) $ateJobId;
			if ( $ateJobId <= 0 ) {
				continue;
			}
			$context                = is_array( $context ) ? $context : [];
			$entries[ $ateJobId ] = [
				'endpoint'    => in_array( $endpoint, [ self::ENDPOINT_HIDE, self::ENDPOINT_DECLINE, self::ENDPOINT_APPLY_FIRST ], true ) ? $endpoint : self::ENDPOINT_CANCEL,
				'reason'      => (string) $reason,
				'source'      => self::sourceOf( $context ),
				'force'       => (bool) $force,
				'at'          => time(),
				'attempts'    => 0,
				'wpml_job_id' => isset( $context['wpml_job_id'] ) ? (int) $context['wpml_job_id'] : 0,
				'user_id'     => isset( $context['user_id'] ) ? (int) $context['user_id'] : (int) get_current_user_id(),
			];

			if ( self::ENDPOINT_APPLY_FIRST === $endpoint ) {
				$entries[ $ateJobId ]['post_id']  = isset( $context['post_id'] ) ? (int) $context['post_id'] : 0;
				$entries[ $ateJobId ]['language'] = isset( $context['language'] ) ? (string) $context['language'] : '';
			}
		}

		if ( ! $entries ) {
			return;
		}

		$dropped = [];
		$stored  = self::mutate( function ( array $stored ) use ( $entries, &$dropped ) {
			foreach ( $entries as $ateJobId => $entry ) {
				$stored['jobs'][ (string) $ateJobId ] = $entry;
			}

			$capped  = array_filter( $stored['jobs'], fn( $entry ) => ! self::isLeftToTheRun( $entry ) );
			$excess  = count( $capped ) - self::CAP;
			$dropped = $excess > 0 ? array_map( 'intval', array_keys( array_slice( $capped, 0, $excess, true ) ) ) : [];
			foreach ( $dropped as $ateJobId ) {
				unset( $stored['jobs'][ (string) $ateJobId ] );
			}

			return $stored;
		} );

		if ( ! self::$inPass ) {
			self::$owedInThisRequest = true;
		}

		$firstEntry = reset( $entries );
		JobLog::runAsBackgroundWork( self::BACKGROUND_LABEL, function () use ( $entries, $firstEntry, $endpoint, $reason, $stored, $dropped, $firstTry ) {
			JobLog::runInGroup( JobLog::GROUP_ID_CANCELS, 'ATE cancel owed', function () use ( $entries, $firstEntry, $endpoint, $reason, $stored, $dropped, $firstTry ) {
				if ( $firstTry instanceof \WP_Error && \WPML_TM_ATE_API::isGatewayErrorAnswer( $firstTry ) ) {
					JobLog::addError( 'ate_cancel_first_try_gateway_error', [
						'ate_job_ids'  => array_keys( $entries ),
						'wpml_job_ids' => array_values( array_column( $entries, 'wpml_job_id' ) ),
						'endpoint'     => $endpoint,
						'reason'       => $reason,
						'http_status'  => $firstTry->get_error_code(),
						'message'      => $firstTry->get_error_message(),
						'body'         => $firstTry->get_error_data(),
						'user_id'      => (int) $firstEntry['user_id'],
					] );
				}

				JobLog::add( 'ate_cancel_pending', [
					'ate_job_ids'  => array_keys( $entries ),
					'wpml_job_ids' => array_values( array_column( $entries, 'wpml_job_id' ) ),
					'endpoint'     => $endpoint,
					'reason'       => $reason,
					'user_id'      => (int) $firstEntry['user_id'],
					'pending'      => count( $stored['jobs'] ),
				] );

				if ( $dropped ) {
					JobLog::addError( 'ate_cancel_pending_dropped', [
						'ate_job_ids' => $dropped,
						'cap'         => self::CAP,
					] );
				}
			} );
		} );
	}

	public static function forget( array $ateJobIds ) {
		$ateJobIds = self::ids( $ateJobIds );
		if ( ! $ateJobIds ) {
			return;
		}

		self::mutate( function ( array $stored ) use ( $ateJobIds ) {
			foreach ( $ateJobIds as $ateJobId ) {
				unset( $stored['jobs'][ (string) $ateJobId ] );
			}

			return $stored;
		} );
	}

	public static function declineOrOwe( array $wpmlJobIdByAteJobId, $reason, $userId = null ) {
		$result  = [ 'declined' => [], 'refused' => [], 'owed' => [] ];
		$owed    = [];
		$decline = self::decliner();

		foreach ( $wpmlJobIdByAteJobId as $ateJobId => $wpmlJobId ) {
			$ateJobId = (int) $ateJobId;
			if ( $ateJobId <= 0 ) {
				continue;
			}

			$code = $decline->sendForCode( $ateJobId, (int) $wpmlJobId, $reason );

			if ( $code >= 200 && $code < 300 ) {
				$result['declined'][] = $ateJobId;
			} elseif ( Decline::isDecided( $code ) ) {
				$result['refused'][] = $ateJobId;
			} else {
				$result['owed'][]  = $ateJobId;
				$owed[ $ateJobId ] = [ 'wpml_job_id' => (int) $wpmlJobId ] + ( null === $userId ? [] : [ 'user_id' => (int) $userId ] );
			}
		}

		$decided = array_merge( $result['declined'], $result['refused'] );
		if ( $decided ) {
			self::forget( $decided );
		}

		if ( $owed ) {
			self::rememberEach( $owed, self::ENDPOINT_DECLINE, $reason );
		}

		return $result;
	}

	public static function oweDecline( array $wpmlJobIdByAteJobId, $reason ) {
		$owed = [];
		foreach ( $wpmlJobIdByAteJobId as $ateJobId => $wpmlJobId ) {
			$owed[ (int) $ateJobId ] = [ 'wpml_job_id' => (int) $wpmlJobId ];
		}

		self::rememberEach( $owed, self::ENDPOINT_DECLINE, $reason );
	}

	public static function oweApplyFirst( $ateJobId, $wpmlJobId, $postId, $language, $source ) {
		self::rememberEach(
			[
				(int) $ateJobId => [
					'wpml_job_id' => (int) $wpmlJobId,
					'post_id'     => (int) $postId,
					'language'    => (string) $language,
					'source'      => (string) $source,
				],
			],
			self::ENDPOINT_APPLY_FIRST,
			''
		);
	}

	private static function decliner() {
		$decline = make( Decline::class );

		return $decline instanceof Decline ? $decline : new Decline();
	}

	public static function pending() {
		return array_map( 'intval', array_keys( self::read()['jobs'] ) );
	}

	public static function retry( \WPML_TM_ATE_API $api, $deadline = 0.0 ) {
		$stored = self::read();
		$result = [ 'sent' => [], 'kept' => [], 'failed' => [], 'budget_left' => false ];

		if ( ! $stored['jobs'] ) {
			return $result;
		}

		$now      = time();
		$deadline = max( 0.0, (float) $deadline );

		if ( ! ( $deadline > 0 && $stored['budget_left'] ) && $now - (int) $stored['last_try'] < self::ADMIN_RETRY_INTERVAL ) {
			$result['kept'] = array_map( 'intval', array_keys( $stored['jobs'] ) );

			return $result;
		}

		return JobLog::runAsBackgroundWork( self::BACKGROUND_LABEL, function () use ( $api, $stored, $now, $result, $deadline ) {
			return JobLog::runInGroup(
				JobLog::GROUP_ID_CANCELS,
				'Owed ATE cancels replayed',
				function () use ( $api, $stored, $now, $result, $deadline ) {
					self::$inPass = true;
					try {
						return self::pass( $api, $stored, $now, $result, $deadline );
					} finally {
						self::$inPass = false;
					}
				}
			);
		} );
	}

	public static function replayAfterRun( $deadline = 0.0 ) {
		$outcome = [ 'replayed' => false, 'skipped' => '', 'sent' => [], 'kept' => [], 'failed' => [], 'budget_left' => false ];
		$stored  = self::read();

		if ( ! $stored['jobs'] ) {
			return [ 'skipped' => self::RUN_REPLAY_NOTHING_OWED ] + $outcome;
		}

		$owed = array_map( 'intval', array_keys( $stored['jobs'] ) );

		if ( self::$owedInThisRequest ) {
			return [ 'skipped' => self::RUN_REPLAY_OWED_HERE, 'kept' => $owed ] + $outcome;
		}

		if ( ! $stored['budget_left'] && time() - (int) $stored['last_try'] < self::ADMIN_RETRY_INTERVAL ) {
			return [ 'skipped' => self::RUN_REPLAY_NOT_DUE, 'kept' => $owed ] + $outcome;
		}

		if ( \WPML_TM_ATE_API::isUnreachableWindowLive() ) {
			return [ 'skipped' => self::RUN_REPLAY_UNREACHABLE, 'kept' => $owed ] + $outcome;
		}

		$api = make( \WPML_TM_ATE_API::class );
		if ( ! $api instanceof \WPML_TM_ATE_API ) {
			return [ 'skipped' => self::RUN_REPLAY_NO_API_CLIENT, 'kept' => $owed ] + $outcome;
		}

		$lock = make( Lock::class, [ ':name' => self::REPLAY_LOCK ] );
		if ( ! $lock->create( self::REPLAY_LOCK_TIMEOUT ) ) {
			return [ 'skipped' => self::RUN_REPLAY_BUSY, 'kept' => $owed ] + $outcome;
		}

		try {
			return [ 'replayed' => true ] + self::retry( $api, (float) $deadline ) + $outcome;
		} finally {
			$lock->release();
		}
	}

	public static function owedCount() {
		$head = self::head();

		return count( $head['jobs'] ) + $head['run_owed'];
	}

	public static function owedForRun() {
		return self::head()['run_owed'];
	}

	public static function isOwedForRun( $ateJobId ) {
		if ( self::head()['run_owed'] < 1 ) {
			return false;
		}
		$entry = self::read()['jobs'][ (string) (int) $ateJobId ] ?? null;

		return null !== $entry && self::isLeftToTheRun( $entry );
	}

	public static function answerSent() {
		self::$owedInThisRequest = false;
	}

	private static function isLeftToTheRun( $entry ) {
		return is_array( $entry ) && in_array( $entry['source'] ?? '', self::RUN_SOURCES, true );
	}

	public static function resetRequestState() {
		self::$owedInThisRequest = false;
		self::$inPass            = false;
	}

	private static function pass( \WPML_TM_ATE_API $api, array $stored, $now, array $result, $deadline = 0.0 ) {
		$expired = [];
		foreach ( $stored['jobs'] as $ateJobId => $entry ) {
			if ( $now - (int) $entry['at'] > self::MAX_AGE ) {
				$expired[] = (int) $ateJobId;
				unset( $stored['jobs'][ $ateJobId ] );
			}
		}

		if ( $expired ) {
			JobLog::addError( 'ate_cancel_pending_expired', [
				'ate_job_ids' => $expired,
				'max_age'     => self::MAX_AGE,
			] );
		}

		$budget   = $deadline > 0 ? new ReplayBudget( $deadline ) : null;
		$replayed = $budget ? $stored['jobs'] : array_filter( $stored['jobs'], fn( $entry ) => ! self::isLeftToTheRun( $entry ) );
		$later    = array_map( 'intval', array_keys( array_diff_key( $stored['jobs'], $replayed ) ) );

		$expiredKeys = array_flip( array_map( 'strval', $expired ) );

		if ( ! $budget && ! $replayed ) {
			if ( $expired ) {
				self::mutate( fn( array $fresh ) => [ 'jobs' => array_diff_key( $fresh['jobs'], $expiredKeys ) ] + $fresh );
			}
			$result['kept'] = $later;

			return $result;
		}

		self::mutate( function ( array $fresh ) use ( $expiredKeys, $now, $budget ) {
			$fresh['jobs']     = array_diff_key( $fresh['jobs'], $expiredKeys );
			$fresh['last_try'] = $now;
			if ( $budget ) {
				$fresh['budget_left'] = false;
			}

			return $fresh;
		} );

		if ( ! $stored['jobs'] ) {
			return $result;
		}

		$groups = [];
		foreach ( $replayed as $ateJobId => $entry ) {
			$source = CancelSource::replay( $entry['source'] ?? '' );
			$key    = $entry['endpoint'] . '|' . $entry['reason'] . '|' . ( $entry['force'] ? 1 : 0 ) . '|' . $source;

			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = [ $entry['endpoint'], $entry['reason'], (bool) $entry['force'], $source, [] ];
			}
			$groups[ $key ][4][] = (int) $ateJobId;
		}

		$unreachable = false;
		$ateAnswered = false;
		$stuck       = [];
		$dialled     = [];
		$appliedFirst = false;
		foreach ( $groups as $group ) {
			list( $endpoint, $reason, $force, $source, $ateJobIds ) = $group;

			while ( $ateJobIds ) {
				$size = $budget ? $budget->nextSize( self::REPLAY_CHUNK ) : self::REPLAY_CHUNK;
				if ( $unreachable || $result['budget_left'] || $size < 1 ) {
					$result['kept']        = array_merge( $result['kept'], $ateJobIds );
					$result['budget_left'] = ! $unreachable;
					break;
				}
				$chunk = array_splice( $ateJobIds, 0, $size );

				if ( self::ENDPOINT_APPLY_FIRST === $endpoint ) {
					self::replayApplyFirst( $chunk, $stored['jobs'] );
					$appliedFirst = true;
					continue;
				}

				if ( self::ENDPOINT_DECLINE === $endpoint ) {
					$startedAt = $budget ? $budget->now() : 0.0;
					$outcome   = self::replayDeclines( $reason, $chunk, $stored['jobs'] );
					if ( $budget ) {
						$budget->took( count( $outcome['dialled'] ), $budget->now() - $startedAt );
					}

					$dialled        = array_merge( $dialled, $outcome['dialled'] );
					$result['sent'] = array_merge( $result['sent'], $outcome['sent'] );
					$result['kept'] = array_merge( $result['kept'], $outcome['kept'] );
					$unreachable    = $outcome['unreachable'];
					$ateAnswered    = $ateAnswered || $outcome['ate_answered'];
					continue;
				}

				$startedAt = $budget ? $budget->now() : 0.0;
				$response  = self::dial( $api, $endpoint, $reason, $force, $chunk, $source, $budget ? $budget->timeout() : self::REPLAY_TIMEOUT );
				if ( $budget ) {
					$budget->took( count( $chunk ), $budget->now() - $startedAt );
				}

				if ( self::wasDialled( $response ) ) {
					$dialled = array_merge( $dialled, $chunk );
				}

				if ( self::isUnanswered( $response ) ) {
					$unreachable    = self::stopsThePass( $response );
					$result['kept'] = array_merge( $result['kept'], $chunk );
					JobLog::addError( 'ate_cancel_retry_unanswered', [
						'ate_job_ids' => $chunk,
						'endpoint'    => $endpoint,
						'dialled'     => self::wasDialled( $response ),
						'error'       => is_wp_error( $response ) ? $response->get_error_message() : null,
						'code'        => is_wp_error( $response ) ? $response->get_error_code() : null,
					] );
					continue;
				}

				if ( \WPML_TM_ATE_API::isGatewayErrorAnswer( $response ) ) {
					$outcome = self::handleGatewayFailure( $api, $endpoint, $reason, $force, $chunk, $response, $stored['jobs'], $source );

					$result['sent']   = array_merge( $result['sent'], $outcome['sent'] );
					$result['kept']   = array_merge( $result['kept'], $outcome['kept'] );
					$result['failed'] = array_merge( $result['failed'], $outcome['failed'] );
					$unreachable      = $outcome['unreachable'];
					$ateAnswered      = $ateAnswered || $outcome['ate_answered'];
					if ( $outcome['stuck'] ) {
						$stuck[] = [ $endpoint, $reason, $force, $outcome['stuck'], $source ];
					}
					continue;
				}

				$ateAnswered = true;
				if ( ! is_wp_error( $response ) && CancelSource::replay( CancelSource::TRANSLATE_EVERYTHING_OFF ) === $source ) {
					do_action( self::OWED_CANCEL_ANSWERED_ACTION, $response, $chunk, $source );
				}
				JobLog::add( 'ate_cancel_retried', [
					'ate_job_ids' => $chunk,
					'endpoint'    => $endpoint,
					'reason'      => $reason,
					'source'      => $source,
					'refused'     => is_wp_error( $response ) ? $response->get_error_code() : null,
				] );

				$stillOwed      = self::followUnconfirmed( $api, $endpoint, $reason, $chunk, $response, $stored['jobs'] );
				$result['sent'] = array_merge( $result['sent'], array_values( array_diff( $chunk, $stillOwed ) ) );
				$result['kept'] = array_merge( $result['kept'], $stillOwed );
			}
		}

		if ( $stuck ) {
			$ateIsUp = $ateAnswered && ! $unreachable;

			if ( ! $ateIsUp && ! $unreachable && self::isTheOnlyWork( $stuck, $result ) && self::keepsFailing( $stuck[0][3], $stored['jobs'] ) ) {
				$ateIsUp     = self::ateAnswersOtherCalls( $api, $stuck[0][3] );
				$ateAnswered = $ateAnswered || $ateIsUp;
			}

			$result = self::resolveStuck( $api, $stuck, $ateIsUp, $stored['jobs'], $result );
		}

		$result['kept'] = array_merge( $result['kept'], $later );

		self::settle( array_merge( $result['sent'], $result['failed'] ), $dialled );

		if ( $result['budget_left'] ) {
			self::mutate( fn( array $fresh ) => [ 'budget_left' => true ] + $fresh );

			if ( ! $dialled ) {
				return $result;
			}
		}

		if ( $appliedFirst && ! $dialled && ! $result['sent'] && ! $result['failed'] && ! $result['kept'] ) {
			return $result;
		}

		$isDown = ! $ateAnswered && ! $result['sent'] && ! $result['failed'] && $result['kept'];
		self::recordAteDown( (bool) $isDown, ! $dialled ? 'reconnecting' : ( $unreachable ? 'unreachable' : 'nothing_answered' ), $result['kept'], $now, $stored['down'] );

		return $result;
	}

	private static function followUnconfirmed( \WPML_TM_ATE_API $api, $endpoint, $reason, array $chunk, $response, array $entries ) {
		if ( is_wp_error( $response ) ) {
			return [];
		}

		$isSupersede = self::ENDPOINT_HIDE === $endpoint && \WPML_TM_ATE_API::CANCEL_REASON_SUPERSEDED === $reason;
		if ( self::ENDPOINT_CANCEL !== $endpoint && ! $isSupersede ) {
			return [];
		}

		$confirmed   = AteResponse::getConfirmedJobIds( $response );
		$unconfirmed = array_values( array_diff( $chunk, is_array( $confirmed ) ? $confirmed : [] ) );
		if ( ! $unconfirmed ) {
			return [];
		}

		if ( $isSupersede ) {
			return self::declineUnconfirmed( $unconfirmed, Decline::SUPERSEDED, $entries );
		}

		$statuses = self::readStatuses( $api, $unconfirmed );
		if ( null === $statuses ) {
			JobLog::addError( 'ate_cancel_unconfirmed_kept', [
				'ate_job_ids' => $unconfirmed,
				'endpoint'    => $endpoint,
				'why'         => 'status_read_failed',
			] );

			return $unconfirmed;
		}

		$completed = [];
		$atWork    = [];
		foreach ( $unconfirmed as $ateJobId ) {
			$status = array_key_exists( $ateJobId, $statuses ) ? $statuses[ $ateJobId ] : null;

			if ( in_array( $status, self::ATE_STATUSES_COMPLETED, true ) ) {
				$completed[] = $ateJobId;
			} elseif ( in_array( $status, self::ATE_STATUSES_STILL_AT_WORK, true ) ) {
				$atWork[] = $ateJobId;
			}
		}

		$settled = array_values( array_diff( $unconfirmed, $completed, $atWork ) );
		JobLog::add( 'ate_cancel_unconfirmed_followed', [
			'ate_job_ids' => $unconfirmed,
			'endpoint'    => $endpoint,
			'statuses'    => $statuses,
			'completed'   => $completed,
			'still_owed'  => $atWork,
			'settled'     => $settled,
		] );

		return array_merge( $atWork, $completed ? self::declineUnconfirmed( $completed, Decline::CANCELLED_ON_SITE, $entries ) : [] );
	}

	private static function declineUnconfirmed( array $ateJobIds, $reason, array $entries ) {
		$decline = self::decliner();
		$owed    = [];
		$codes   = [];
		foreach ( $ateJobIds as $ateJobId ) {
			$code               = $decline->sendForCode( $ateJobId, (int) ( $entries[ (string) $ateJobId ]['wpml_job_id'] ?? 0 ), $reason );
			$codes[ $ateJobId ] = $code;
			if ( ! Decline::isDecided( $code ) ) {
				$owed[] = $ateJobId;
			}
		}

		JobLog::add( 'ate_cancel_unconfirmed_declined', [
			'ate_job_ids' => $ateJobIds,
			'reason'      => $reason,
			'codes'       => $codes,
			'owed'        => $owed,
		] );

		if ( $owed ) {
			self::convertToDecline( $owed, $reason );
		}

		return $owed;
	}

	private static function convertToDecline( array $ateJobIds, $reason ) {
		self::mutate( function ( array $stored ) use ( $ateJobIds, $reason ) {
			foreach ( $ateJobIds as $ateJobId ) {
				if ( ! isset( $stored['jobs'][ (string) $ateJobId ] ) ) {
					continue;
				}
				$stored['jobs'][ (string) $ateJobId ]['endpoint'] = self::ENDPOINT_DECLINE;
				$stored['jobs'][ (string) $ateJobId ]['reason']   = (string) $reason;
				$stored['jobs'][ (string) $ateJobId ]['force']    = false;
			}

			return $stored;
		} );
	}

	private static function replayDeclines( $reason, array $chunk, array $entries ) {
		$outcome = [ 'sent' => [], 'kept' => [], 'dialled' => [], 'unreachable' => false, 'ate_answered' => false ];
		$decline = self::decliner();
		$codes   = [];

		foreach ( $chunk as $ateJobId ) {
			if ( $outcome['unreachable'] ) {
				$outcome['kept'][] = $ateJobId;
				continue;
			}

			$code                 = $decline->sendForCode( $ateJobId, (int) ( $entries[ (string) $ateJobId ]['wpml_job_id'] ?? 0 ), $reason );
			$codes[ $ateJobId ]   = $code;
			$outcome['dialled'][] = $ateJobId;

			if ( Decline::isDecided( $code ) ) {
				$outcome['sent'][]       = $ateJobId;
				$outcome['ate_answered'] = true;
				continue;
			}

			$outcome['kept'][]       = $ateJobId;
			$outcome['unreachable']  = 0 === $code;
			$outcome['ate_answered'] = $outcome['ate_answered'] || $code > 0;
		}

		JobLog::add( 'ate_decline_retried', [
			'ate_job_ids' => $chunk,
			'reason'      => $reason,
			'codes'       => $codes,
			'kept'        => $outcome['kept'],
		] );

		return $outcome;
	}

	private static function replayApplyFirst( array $chunk, array $entries ) {
		self::forget( $chunk );

		$byPost = [];
		foreach ( $chunk as $ateJobId ) {
			$entry = isset( $entries[ (string) $ateJobId ] ) ? $entries[ (string) $ateJobId ] : null;

			if ( ! is_array( $entry ) || empty( $entry['post_id'] ) || '' === (string) ( $entry['language'] ?? '' ) ) {
				continue;
			}

			$postId                     = (int) $entry['post_id'];
			$byPost[ $postId ]['source'] = (string) ( $entry['source'] ?? '' );

			$byPost[ $postId ]['items'][ (string) $entry['language'] ] = [
				'job_id'     => (int) ( $entry['wpml_job_id'] ?? 0 ),
				'ate_job_id' => (int) $ateJobId,
			];
		}

		JobLog::add( 'ate_apply_first_replayed', [
			'ate_job_ids' => array_values( array_map( 'intval', $chunk ) ),
			'post_ids'    => array_keys( $byPost ),
		] );

		foreach ( $byPost as $postId => $save ) {
			\WPML\TM\AutomaticTranslation\Actions\SaveOverFinished::settleAndDispatch( $postId, $save['items'], $save['source'] );
		}
	}

	private static function isTheOnlyWork( array $stuck, array $result ) {
		return 1 === count( $stuck ) && ! $result['sent'] && ! $result['failed'] && ! $result['kept'];
	}

	private static function keepsFailing( array $ateJobIds, array $entries ) {
		foreach ( $ateJobIds as $ateJobId ) {
			if ( (int) ( $entries[ (string) $ateJobId ]['attempts'] ?? 0 ) < 1 ) {
				return false;
			}
		}

		return (bool) $ateJobIds;
	}

	private static function ateAnswersOtherCalls( \WPML_TM_ATE_API $api, array $ateJobIds ) {
		try {
			$answer = $api->get_catalogue_version();
		} catch ( \Throwable $e ) {
			$answer = null;
		}

		$answered = is_array( $answer );

		JobLog::add( 'ate_cancel_lone_group_probe', [
			'ate_job_ids' => $ateJobIds,
			'call'        => 'GET catalogue version',
			'answered'    => $answered,
			'verdict'     => $answered ? 'ATE answers other calls: the group is decided as a failing group' : 'no 2xx: ATE down for the site, everything kept',
		] );

		return $answered;
	}

	private static function recordAteDown( $isDown, $reason, array $kept, $now, $before ) {
		if ( $isDown ) {
			$since = $before ? (int) $before['since'] : (int) $now;
			if ( ! $before ) {
				self::mutate( fn( array $stored ) => [ 'down' => [ 'since' => $since, 'reason' => $reason ] ] + $stored );
			}

			JobLog::addError( 'ate_cancel_kept_ate_down', [
				'ate_job_ids' => array_values( array_map( 'intval', $kept ) ),
				'reason'      => $before ? (string) $before['reason'] : $reason,
				'since'       => $since,
				'down_for'    => max( 0, (int) $now - $since ),
				'shown'       => 'on the ATE screens until a pass is answered',
			] );

			return;
		}

		if ( ! $before ) {
			return;
		}

		self::mutate( fn( array $stored ) => [ 'down' => null ] + $stored );

		JobLog::add( 'ate_cancel_ate_answered_again', [
			'down_for' => max( 0, (int) $now - (int) $before['since'] ),
			'since'    => (int) $before['since'],
		] );
	}

	public static function ateDown() {
		$head = self::head();
		$kept = count( $head['jobs'] ) + $head['run_owed'];

		if ( null === $head['down'] || ! $kept ) {
			return null;
		}

		return [
			'since'  => (int) $head['down']['since'],
			'reason' => (string) $head['down']['reason'],
			'kept'   => $kept,
		];
	}

	private static function resolveStuck( \WPML_TM_ATE_API $api, array $stuck, $ateIsUp, array $entries, array $result ) {
		if ( ! $ateIsUp ) {
			$ids = [];
			foreach ( $stuck as $chunk ) {
				$ids = array_merge( $ids, $chunk[3] );
			}

			JobLog::addError( 'ate_cancel_ate_down', [
				'reason'      => 'status_read_failed_for_every_group',
				'ate_job_ids' => $ids,
				'kept'        => 'everything owed, next pass in ' . self::ADMIN_RETRY_INTERVAL . ' s',
			] );
			$result['kept'] = array_merge( $result['kept'], $ids );

			return $result;
		}

		foreach ( $stuck as $index => $chunk ) {
			list( $endpoint, $reason, $force, $ids, $source ) = $chunk;

			if ( 1 === count( $ids ) ) {
				self::giveUp( $ids[0], $entries[ (string) $ids[0] ] ?? [], 'status_read_failed_while_ate_answered', null );
				$result['failed'][] = $ids[0];
				continue;
			}

			$outcome        = self::split( $api, $endpoint, $reason, $force, $ids, $source );
			$result['sent'] = array_merge( $result['sent'], $outcome['sent'] );
			$result['kept'] = array_merge( $result['kept'], $outcome['kept'] );

			if ( $outcome['unreachable'] ) {
				foreach ( array_slice( $stuck, $index + 1 ) as $rest ) {
					$result['kept'] = array_merge( $result['kept'], $rest[3] );
				}

				return $result;
			}
		}

		return $result;
	}

	private static function dial( \WPML_TM_ATE_API $api, $endpoint, $reason, $force, array $ateJobIds, $source, $timeout = self::REPLAY_TIMEOUT ) {
		try {
			return self::ENDPOINT_HIDE === $endpoint
				? $api->hideJobs( $ateJobIds, $force, $reason, self::requestArgs( $timeout ), $source )
				: $api->cancelJobs( $ateJobIds, false, $reason, self::requestArgs( $timeout ), $source );
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	private static function sourceOf( array $context ) {
		return isset( $context['source'] ) && is_scalar( $context['source'] ) ? (string) $context['source'] : '';
	}

	private static function requestArgs( $timeout = self::REPLAY_TIMEOUT ) {
		return [
			'timeout'                                => (int) $timeout,
			\WPML_TM_ATE_API::RAW_GATEWAY_ERROR_ARG => true,
		];
	}

	/**
	 * ATE answered the replayed call with a 5xx (wpmldev-11664, ruling 1).
	 *
	 * (a) One status read of the chunk's ids. A read that never reached ATE
	 *     (the transport, a 503, a 426, the reconnect episode, a throw) means
	 *     ATE is down: everything stays owed and the pass stops. A read ATE
	 *     answered with an error, or with no status in it, says nothing
	 *     about the site (A-F1, Ivan's ruling "a"): the chunk is stuck, and
	 *     is decided after every other group of the pass was dialled
	 *     ({@see self::resolveStuck()}).
	 * (b) Every id ATE shows cancelled is settled, and so is an id ATE no
	 *     longer knows.
	 * (c) Some moved: the first id, in the order WPML sent, that did not move
	 *     is the job whose cancel fails (ATE cancels one by one and keeps no
	 *     order, so the order is WPML's). It is given up
	 *     ({@see self::giveUp()}); the rest stay owed for the next pass.
	 * (d) None moved: the chunk is split once, in halves, each dialled once.
	 *     Both halves fail: that chunk stays owed; ATE answered its status,
	 *     so ATE is up and the other groups still go out (A-F1). One half
	 *     answers: it is settled, the other stays owed, narrower, for the
	 *     next pass. A chunk of one id cannot be split: ATE answered its
	 *     status, so ATE is up and this is the failed job.
	 *
	 * @param \WPML_TM_ATE_API $api
	 * @param string           $endpoint
	 * @param string           $reason
	 * @param bool             $force
	 * @param int[]            $chunk
	 * @param \WP_Error        $response The raw gateway error.
	 * @param array            $entries  The stored entries, by ATE id.
	 * @param string           $source   The replay's source (wpmldev-12746).
	 *
	 * @return array{sent: int[], kept: int[], failed: int[], stuck: int[], unreachable: bool, ate_answered: bool}
	 *         `stuck`: the chunk whose status read ATE answered with an
	 *         error, still undecided; `ate_answered`: ATE answered a call of
	 *         this chunk with something other than a gateway error.
	 */
	private static function handleGatewayFailure( \WPML_TM_ATE_API $api, $endpoint, $reason, $force, array $chunk, \WP_Error $response, array $entries, $source ) {
		$outcome = [ 'sent' => [], 'kept' => [], 'failed' => [], 'stuck' => [], 'unreachable' => false, 'ate_answered' => false ];

		JobLog::addError( 'ate_cancel_retry_gateway_error', [
			'ate_job_ids' => $chunk,
			'endpoint'    => $endpoint,
			'reason'      => $reason,
			'http_status' => $response->get_error_code(),
			'message'     => $response->get_error_message(),
			'body'        => $response->get_error_data(),
		] );

		$readReachedATE = false;
		$statuses       = self::readStatuses( $api, $chunk, $readReachedATE );

		if ( null === $statuses && ! $readReachedATE ) {
			JobLog::addError( 'ate_cancel_ate_down', [
				'reason'      => 'status_read_failed',
				'ate_job_ids' => $chunk,
				'kept'        => 'everything owed, next pass in ' . self::ADMIN_RETRY_INTERVAL . ' s',
			] );
			$outcome['kept']        = $chunk;
			$outcome['unreachable'] = true;

			return $outcome;
		}

		if ( null === $statuses ) {
			JobLog::addError( 'ate_cancel_group_stuck', [
				'reason'      => 'status_read_failed',
				'ate_job_ids' => $chunk,
				'endpoint'    => $endpoint,
				'decided'     => 'after the other groups of this pass',
			] );
			$outcome['stuck'] = $chunk;

			return $outcome;
		}

		$outcome['ate_answered'] = true;

		$moved    = [];
		$notMoved = [];
		foreach ( $chunk as $ateJobId ) {
			if ( ! array_key_exists( $ateJobId, $statuses ) || self::settledByStatus( $endpoint, $force, $statuses[ $ateJobId ] ) ) {
				$moved[] = $ateJobId;
			} else {
				$notMoved[] = $ateJobId;
			}
		}

		if ( $moved ) {
			JobLog::add( 'ate_cancel_settled_by_status', [
				'ate_job_ids' => $moved,
				'endpoint'    => $endpoint,
				'not_held'    => array_values( array_diff( $moved, array_keys( $statuses ) ) ),
			] );
			$outcome['sent'] = $moved;
		}

		if ( ! $notMoved ) {
			return $outcome;
		}

		if ( $moved || 1 === count( $notMoved ) ) {
			$failed = $notMoved[0];
			$rest   = array_slice( $notMoved, 1 );

			self::giveUp( $failed, $entries[ (string) $failed ] ?? [], $moved ? 'first_not_cancelled_after_partial_cancel' : 'only_id_of_the_call', $statuses[ $failed ] ?? null );
			$outcome['failed'] = [ $failed ];
			$outcome['kept']   = $rest;

			if ( $rest ) {
				JobLog::add( 'ate_cancel_kept_for_next_pass', [
					'ate_job_ids' => $rest,
					'endpoint'    => $endpoint,
				] );
			}

			return $outcome;
		}

		$split                  = self::split( $api, $endpoint, $reason, $force, $notMoved, $source );
		$outcome['sent']        = array_merge( $outcome['sent'], $split['sent'] );
		$outcome['kept']        = array_merge( $outcome['kept'], $split['kept'] );
		$outcome['unreachable'] = $split['unreachable'];

		return $outcome;
	}

	private static function split( \WPML_TM_ATE_API $api, $endpoint, $reason, $force, array $ateJobIds, $source ) {
		$outcome  = [ 'sent' => [], 'kept' => [], 'unreachable' => false ];
		$halves   = array_chunk( $ateJobIds, max( 1, (int) ceil( count( $ateJobIds ) / 2 ) ) );
		$verdicts = [];
		$failing  = 0;
		foreach ( $halves as $half ) {
			$answer = self::dial( $api, $endpoint, $reason, $force, $half, $source );

			if ( \WPML_TM_ATE_API::isGatewayErrorAnswer( $answer ) ) {
				$verdicts[]      = [ 'ate_job_ids' => $half, 'outcome' => 'gateway_error', 'http_status' => $answer->get_error_code() ];
				$outcome['kept'] = array_merge( $outcome['kept'], $half );
				$failing ++;
				continue;
			}

			if ( self::isUnanswered( $answer ) ) {
				$verdicts[]      = [ 'ate_job_ids' => $half, 'outcome' => 'unanswered', 'code' => is_wp_error( $answer ) ? $answer->get_error_code() : null ];
				$outcome['kept'] = array_merge( $outcome['kept'], $half );
				$failing ++;
				if ( self::stopsThePass( $answer ) ) {
					$outcome['unreachable'] = true;
				}
				continue;
			}

			$verdicts[]      = [ 'ate_job_ids' => $half, 'outcome' => 'answered', 'refused' => is_wp_error( $answer ) ? $answer->get_error_code() : null ];
			$outcome['sent'] = array_merge( $outcome['sent'], $half );
		}

		JobLog::add( 'ate_cancel_split', [
			'ate_job_ids' => $ateJobIds,
			'endpoint'    => $endpoint,
			'halves'      => $halves,
			'verdicts'    => $verdicts,
		] );

		if ( $failing === count( $halves ) && ! $outcome['unreachable'] ) {
			JobLog::addError( 'ate_cancel_group_stuck', [
				'reason'      => 'both_halves_failed',
				'ate_job_ids' => $ateJobIds,
				'endpoint'    => $endpoint,
				'kept'        => 'this chunk owed, next pass in ' . self::ADMIN_RETRY_INTERVAL . ' s; the other groups go out',
			] );
		}

		return $outcome;
	}

	private static function settledByStatus( $endpoint, $force, $status ) {
		if ( in_array( $status, \WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUSES_CANCELLED, true ) ) {
			return true;
		}

		if ( self::ENDPOINT_HIDE === $endpoint ) {
			return ! $force && \WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_NOT_ENOUGH !== $status;
		}

		return in_array( $status, \WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUSES_FINISHED, true );
	}

	private static function readStatuses( \WPML_TM_ATE_API $api, array $ateJobIds, &$reachedATE = false ) {
		try {
			$answer = $api->get_jobs( $ateJobIds, null, self::requestArgs() );
		} catch ( \Throwable $e ) {
			$answer = null;
		}

		$reachedATE = ! self::isUnanswered( $answer );
		$statuses   = is_wp_error( $answer ) || ! $reachedATE ? null : self::statusesFrom( $answer );

		JobLog::add( 'ate_cancel_status_read', [
			'ate_job_ids' => $ateJobIds,
			'statuses'    => $statuses,
			'error'       => is_wp_error( $answer ) ? [ 'code' => $answer->get_error_code(), 'message' => $answer->get_error_message() ] : null,
			'answered'    => null !== $statuses,
			'reached_ate' => $reachedATE,
			'unreadable'  => null === $statuses && ! is_wp_error( $answer ) && $reachedATE,
		] );

		return $statuses;
	}

	private static function statusesFrom( $answer ) {
		$items = is_object( $answer ) ? get_object_vars( $answer ) : $answer;
		if ( ! is_array( $items ) ) {
			return null;
		}
		if ( isset( $items['jobs'] ) && ( is_array( $items['jobs'] ) || is_object( $items['jobs'] ) ) ) {
			$items = is_object( $items['jobs'] ) ? get_object_vars( $items['jobs'] ) : $items['jobs'];
		}

		$statuses = [];
		foreach ( $items as $key => $item ) {
			$item = is_object( $item ) ? get_object_vars( $item ) : $item;
			if ( ! is_array( $item ) ) {
				continue;
			}

			$status = $item['status_id'] ?? ( $item['status'] ?? null );
			if ( ! is_numeric( $status ) ) {
				continue;
			}

			$id = is_numeric( $key ) ? (int) $key : (int) ( $item['ate_job_id'] ?? ( $item['id'] ?? 0 ) );
			if ( $id > 0 ) {
				$statuses[ $id ] = (int) $status;
			}
		}

		return $statuses ? $statuses : null;
	}

	private static function giveUp( $ateJobId, array $entry, $why, $ateStatus ) {
		$wpmlJobId = (int) ( $entry['wpml_job_id'] ?? 0 );
		$userId    = (int) ( $entry['user_id'] ?? 0 );

		JobLog::addError( 'ate_cancel_failed_job', [
			'ate_job_id'  => $ateJobId,
			'wpml_job_id' => $wpmlJobId,
			'endpoint'    => $entry['endpoint'] ?? null,
			'reason'      => $entry['reason'] ?? null,
			'why'         => $why,
			'ate_status'  => $ateStatus,
			'attempts'    => (int) ( $entry['attempts'] ?? 0 ) + 1,
			'user_id'     => $userId,
			'outcome'     => 'the job stays cancelled in WPML; ATE still holds it',
		] );

		if ( $userId <= 0 ) {
			JobLog::add( 'ate_cancel_notice_skipped', [
				'ate_job_id'  => $ateJobId,
				'wpml_job_id' => $wpmlJobId,
				'reason'      => 'no_user',
				'why'         => 'the cancel was owed by a request with no user (cron, CLI, an import)',
			] );

			return;
		}

		$queued = FailedCancelNotice::queue( $userId, [
			'ate_job_id'  => $ateJobId,
			'wpml_job_id' => $wpmlJobId,
		] );

		JobLog::add( $queued ? 'ate_cancel_notice_queued' : 'ate_cancel_notice_skipped', [
			'ate_job_id'  => $ateJobId,
			'wpml_job_id' => $wpmlJobId,
			'user_id'     => $userId,
			'reason'      => $queued ? null : 'not_stored',
			'shown'       => 'on that user\'s next admin page',
		] );
	}

	private static function settle( array $settled, array $dialled ) {
		if ( ! $settled && ! $dialled ) {
			return;
		}

		self::mutate( function ( array $stored ) use ( $settled, $dialled ) {
			foreach ( $settled as $ateJobId ) {
				unset( $stored['jobs'][ (string) $ateJobId ] );
			}
			foreach ( $dialled as $ateJobId ) {
				if ( isset( $stored['jobs'][ (string) $ateJobId ] ) ) {
					$entry             = $stored['jobs'][ (string) $ateJobId ];
					$entry['attempts'] = ( isset( $entry['attempts'] ) ? (int) $entry['attempts'] : 0 ) + 1;

					$stored['jobs'][ (string) $ateJobId ] = $entry;
				}
			}

			return $stored;
		} );
	}

	public static function retryOnAdminRequest() {
		$stored = self::head();

		if ( ! $stored['jobs'] ) {
			return;
		}

		if ( time() - (int) $stored['last_try'] < self::ADMIN_RETRY_INTERVAL ) {
			return;
		}

		if ( \WPML_TM_ATE_API::isUnreachableWindowLive() ) {
			return;
		}

		$api = make( \WPML_TM_ATE_API::class );
		if ( ! $api instanceof \WPML_TM_ATE_API ) {
			return;
		}

		self::retry( $api );
	}

	private static function ids( array $ateJobIds ) {
		return array_values( array_unique( array_filter( array_map( 'intval', $ateJobIds ) ) ) );
	}

	private static function read( $fresh = false ) {
		$stored = self::head( $fresh );
		foreach ( $stored['rows'] as $row ) {
			if ( $fresh ) {
				self::forgetCached( self::ROW_OPTION_PREFIX . $row );
			}
			$entries = get_option( self::ROW_OPTION_PREFIX . $row, [] );
			$entries = is_array( $entries ) ? $entries : [];

			$stored['row_keys'][ $row ] = array_map( 'strval', array_keys( $entries ) );
			$stored['jobs']            += $entries;
		}

		return $stored;
	}

	private static function head( $fresh = false ) {
		if ( $fresh ) {
			self::forgetCached( self::OPTION );
		}
		$stored = get_option( self::OPTION, [] );
		$stored = is_array( $stored ) ? $stored : [];
		$down   = isset( $stored['down'] ) && is_array( $stored['down'] ) ? $stored['down'] : null;

		return [
			'jobs'        => isset( $stored['jobs'] ) && is_array( $stored['jobs'] ) ? $stored['jobs'] : [],
			'last_try'    => (int) ( $stored['last_try'] ?? 0 ),
			'down'        => $down ? [ 'since' => (int) ( $down['since'] ?? 0 ), 'reason' => (string) ( $down['reason'] ?? '' ) ] : null,
			'budget_left' => ! empty( $stored['budget_left'] ),
			'run_owed'    => (int) ( $stored['run_owed'] ?? 0 ),
			'rows'        => array_values( array_map( 'intval', (array) ( $stored['rows'] ?? [] ) ) ),
			'row_keys'    => [],
		];
	}

	private static function mutate( callable $change ) {
		$lock   = null;
		$locked = false;
		try {
			$lock   = make( AdvisoryLockFactory::class )->create( self::OPTION );
			$locked = $lock->acquire( self::WRITE_LOCK_TIMEOUT );
		} catch ( \Throwable $e ) {
			$locked = false;
		}

		try {
			$stored = $change( self::read( true ) );
			self::write( $stored );

			return $stored;
		} finally {
			if ( $locked ) {
				$lock->release();
			}
		}
	}

	private static function forgetCached( $option ) {
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( $option, 'options' );
			wp_cache_delete( 'notoptions', 'options' );
		}
	}

	private static function write( array $stored ) {
		$main = [];
		$run  = [];
		foreach ( $stored['jobs'] as $ateJobId => $entry ) {
			if ( self::isLeftToTheRun( $entry ) ) {
				$run[ (string) $ateJobId ] = $entry;
			} else {
				$main[ $ateJobId ] = $entry;
			}
		}

		$rows = [];
		foreach ( $stored['row_keys'] ?? [] as $row => $keys ) {
			$rows[ (int) $row ] = array_intersect_key( $run, array_flip( $keys ) );
		}
		$last = $rows ? max( array_keys( $rows ) ) : 0;
		foreach ( array_diff_key( $run, ...array_values( $rows ?: [ [] ] ) ) as $ateJobId => $entry ) {
			if ( ! $last || count( $rows[ $last ] ) >= self::ROW_SIZE ) {
				$rows[ ++$last ] = [];
			}
			$rows[ $last ][ $ateJobId ] = $entry;
		}

		$live = [];
		foreach ( $rows as $row => $entries ) {
			if ( $entries ) {
				update_option( self::ROW_OPTION_PREFIX . $row, $entries, false );
				$live[] = $row;
			} else {
				delete_option( self::ROW_OPTION_PREFIX . $row );
			}
		}

		if ( ! $main && ! $live ) {
			delete_option( self::OPTION );

			return;
		}

		update_option(
			self::OPTION,
			[
				'jobs'        => $main,
				'last_try'    => (int) ( $stored['last_try'] ?? 0 ),
				'down'        => $stored['down'] ?? null,
				'budget_left' => ! empty( $stored['budget_left'] ),
				'run_owed'    => count( $run ),
				'rows'        => $live,
			],
			false
		);
	}
}
