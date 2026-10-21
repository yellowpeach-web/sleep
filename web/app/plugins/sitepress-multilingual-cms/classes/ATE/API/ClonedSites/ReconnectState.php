<?php

namespace WPML\TM\ATE\ClonedSites;

use WPML\API\Settings;
use WPML\FP\Obj;
use WPML\TM\ATE\API\FingerprintGenerator;
use function WPML\Container\make;

class ReconnectState {

	const OPTION = 'wpml_ate_reconnect_state';

	const KIND_SERVER = 'server';

	const KIND_NO_ANSWER = 'no_answer';

	const ESCALATE_AFTER_SECONDS = 14400;

	const PROBE_FLOOR_SECONDS = 20;

	const SUPPORT_AFTER_ATTEMPTS = 3;

	const SUPPORT_AFTER_SECONDS = 120;

	const SERVICE_ATE  = 'ate';
	const SERVICE_AMS  = 'ams';
	const SERVICE_BOTH = 'both';

	const OPENED_BY_CREATE = 'create';
	const OPENED_BY_OTHER  = 'other';

	const BACKOFF_SECONDS = [ 60, 120, 300, 900, 3600, 21600, 86400 ];

	const JITTER_PERCENT = 20;

	const ERROR_LOG_SIZE = 5;

	const DISMISS_FOR_SECONDS = 86400;

	public static function recordFailure( $oldUrl = '', $newUrl = '', $httpStatus = 0, $errorKey = '', $retryAfter = 0, $kind = self::KIND_SERVER, $service = '', $host = '', $reattempt = true, $silence = true, $callKind = '' ) {
		$state = self::readFresh();
		$now   = time();

		$attempts = isset( $state['attempts'] ) ? (int) $state['attempts'] + 1 : 1;
		$first    = ! empty( $state['first_failure_at'] ) ? (int) $state['first_failure_at'] : $now;

		$errors   = isset( $state['last_errors'] ) && is_array( $state['last_errors'] ) ? $state['last_errors'] : [];
		$errors[] = [
			'at'     => $now,
			'status' => (int) $httpStatus,
			'key'    => (string) $errorKey,
		];
		if ( count( $errors ) > self::ERROR_LOG_SIZE ) {
			$errors = array_slice( $errors, - self::ERROR_LOG_SIZE );
		}

		$retryAfter = (int) $retryAfter;

		if ( self::KIND_NO_ANSWER === $kind && self::prop( 'kind', $state, '' ) === self::KIND_SERVER ) {
			$kind = self::KIND_SERVER;
		}

		if ( self::KIND_NO_ANSWER === $kind && $silence ) {
			$nextAttemptAt = $now + ( $retryAfter > 0 ? $retryAfter : self::PROBE_FLOOR_SECONDS );
			$cronDueAt     = $now + self::delayFor( $attempts );
		} else {
			$nextAttemptAt = $now + ( $retryAfter > 0 ? $retryAfter : self::delayFor( $attempts ) );
			$cronDueAt     = $nextAttemptAt;
		}

		$service         = self::normaliseService( $service );
		$failedService   = $service;
		$previousService = self::normaliseService( self::prop( 'service', $state, '' ) );
		if ( '' === $service ) {
			$service = $previousService;
		} elseif ( '' !== $previousService && $previousService !== $service ) {
			$service = self::SERVICE_BOTH;
		}

		$hosts = self::prop( 'hosts', $state, [] );
		$hosts = is_array( $hosts ) ? $hosts : [];
		$host  = (string) $host;
		if ( '' !== $host && in_array( $service, [ self::SERVICE_ATE, self::SERVICE_AMS ], true ) ) {
			$hosts[ $service ] = $host;
		} elseif ( '' !== $host && self::SERVICE_BOTH === $service ) {
			$hosts[ self::SERVICE_ATE ] = isset( $hosts[ self::SERVICE_ATE ] ) ? $hosts[ self::SERVICE_ATE ] : $host;
			$hosts[ self::SERVICE_AMS ] = isset( $hosts[ self::SERVICE_AMS ] ) ? $hosts[ self::SERVICE_AMS ] : $host;
		}

		$new = [
			'first_failure_at' => $first,
			'attempts'         => $attempts,
			'next_attempt_at'  => $nextAttemptAt,
			'last_attempt_at'  => $now,
			'cron_due_at'      => $cronDueAt,
			'last_http_status' => (int) $httpStatus,
			'last_error_key'   => (string) $errorKey,
			'kind'             => (string) $kind,
			'service'          => $service,
			'failed_reattempts' => (int) self::prop( 'failed_reattempts', $state, 0 ) + ( $reattempt ? 1 : 0 ),
			'host'             => '' !== $host ? $host : (string) self::prop( 'host', $state, '' ),
			'hosts'            => $hosts,
			'old_url'          => $oldUrl !== '' ? $oldUrl : self::prop( 'old_url', $state, '' ),
			'new_url'          => $newUrl !== '' ? $newUrl : self::prop( 'new_url', $state, '' ),
			'last_errors'      => $errors,
			'dismissed_code'   => (string) self::prop( 'dismissed_code', $state, '' ),
			'dismissed_until'  => (int) self::prop( 'dismissed_until', $state, 0 ),
			'opened_by'        => ( self::OPENED_BY_CREATE === self::prop( 'opened_by', $state, '' ) || self::OPENED_BY_CREATE === $callKind )
				? self::OPENED_BY_CREATE
				: self::OPENED_BY_OTHER,
		];

		$answered = self::answeredServices( $state );
		if ( self::OPENED_BY_CREATE !== $callKind ) {
			$answered = in_array( $failedService, [ self::SERVICE_ATE, self::SERVICE_AMS ], true )
				? array_values( array_diff( $answered, [ $failedService ] ) )
				: [];
		}
		if ( $answered ) {
			$new['read_answered'] = array_fill_keys( $answered, 1 );
		}

		update_option( self::OPTION, $new, 'no' );

		return $new;
	}

	public static function startDue( $oldUrl = '', $newUrl = '', $errorKey = 'converted_from_lock' ) {
		$now = time();

		$state = [
			'first_failure_at' => $now,
			'attempts'         => 0,
			'next_attempt_at'  => $now,
			'last_http_status' => 0,
			'last_error_key'   => (string) $errorKey,
			'kind'             => self::KIND_SERVER,
			'old_url'          => (string) $oldUrl,
			'new_url'          => (string) $newUrl,
			'last_errors'      => [],
		];

		update_option( self::OPTION, $state, 'no' );

		return $state;
	}

	public static function clear() {
		delete_option( self::OPTION );
		AliasDomainResetFlag::clear();

		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( ReconnectDriver::CRON_HOOK );
		}
	}

	public static function clearUnlessAbout( $oldUrl, $newUrl, $attempted = null ) {
		$state = self::readFresh();

		if (
			$state
			&& '' !== (string) $oldUrl
			&& (string) self::prop( 'old_url', $state, '' ) === (string) $oldUrl
			&& (string) self::prop( 'new_url', $state, '' ) === (string) $newUrl
			&& $state !== $attempted
		) {
			return false;
		}

		self::clear();

		return true;
	}

	public static function makeDue() {
		$state = self::readFresh();

		if ( ! $state ) {
			return false;
		}

		$state['next_attempt_at'] = time();
		$state['cron_due_at']     = time();

		update_option( self::OPTION, $state, 'no' );

		return true;
	}

	public static function dismissEscalation( $code ) {
		$state = self::readFresh();

		if ( ! $state ) {
			return false;
		}

		$state['dismissed_code']  = (string) $code;
		$state['dismissed_until'] = time() + self::DISMISS_FOR_SECONDS;

		update_option( self::OPTION, $state, 'no' );

		return true;
	}

	public static function isEscalationDismissed( $code ) {
		$state = self::read();

		if ( ! $state ) {
			return false;
		}

		if ( (string) self::prop( 'dismissed_code', $state, '' ) !== (string) $code ) {
			return false;
		}

		return (int) self::prop( 'dismissed_until', $state, 0 ) > time();
	}

	public static function urls() {
		$state = self::read();

		return [
			'old_url' => (string) self::prop( 'old_url', $state, '' ),
			'new_url' => (string) self::prop( 'new_url', $state, '' ),
		];
	}

	public static function resolveAsMove() {
		$urls = self::urls();

		if ( $urls['old_url'] && $urls['new_url'] ) {
			Settings::setAndSave( 'migrated_site', $urls );
		}

		self::clear();

		return $urls;
	}

	public static function isReconnecting() {
		$state = self::read();

		if ( ! $state ) {
			return false;
		}

		if (
			! empty( $state['old_url'] )
			&& $state['old_url'] === self::currentUrl()
			&& self::prop( 'new_url', $state, '' ) !== $state['old_url']
		) {
			self::clear();

			return false;
		}

		return \WPML_TM_ATE_Status::is_enabled();
	}

	public static function isDue() {
		$state = self::read();

		if ( ! $state ) {
			return false;
		}

		return (int) self::prop( 'next_attempt_at', $state, 0 ) <= time();
	}

	public static function isNoAnswer() {
		return self::KIND_NO_ANSWER === self::prop( 'kind', self::read(), self::KIND_SERVER );
	}

	public static function openedByCreate() {
		$s = self::read();

		return (bool) $s && self::isNoAnswer() && self::OPENED_BY_CREATE === self::prop( 'opened_by', $s, self::OPENED_BY_OTHER );
	}

	public static function liftFloor( $service = self::SERVICE_ATE ) {
		$state = self::readFresh();

		if ( ! $state || ! self::isNoAnswer() ) {
			return;
		}

		$service = self::SERVICE_AMS === self::normaliseService( $service ) ? self::SERVICE_AMS : self::SERVICE_ATE;

		$now      = time();
		$previous = (int) self::prop( 'next_attempt_at', $state, $now );
		$next     = min( $previous, $now );
		$answered = self::answeredServices( $state );

		if ( $next === $previous && in_array( $service, $answered, true ) ) {
			return;
		}

		$answered[]               = $service;
		$state['next_attempt_at'] = $next;
		$state['read_answered']   = array_fill_keys( array_values( array_intersect( [ self::SERVICE_ATE, self::SERVICE_AMS ], $answered ) ), 1 );

		update_option( self::OPTION, $state, 'no' );
	}

	public static function hasReadAnswered() {
		$state    = self::read();
		$answered = self::answeredServices( $state );

		if ( ! $answered ) {
			return false;
		}

		switch ( self::normaliseService( self::prop( 'service', $state, '' ) ) ) {
			case self::SERVICE_ATE:
				$failed = [ self::SERVICE_ATE ];
				break;
			case self::SERVICE_AMS:
				$failed = [ self::SERVICE_AMS ];
				break;
			case self::SERVICE_BOTH:
				$failed = [ self::SERVICE_ATE, self::SERVICE_AMS ];
				break;
			default:
				return true;
		}

		return ! array_diff( $failed, $answered );
	}

	private static function answeredServices( $state ) {
		$answered = self::prop( 'read_answered', $state, [] );

		if ( is_array( $answered ) ) {
			return array_values( array_intersect( [ self::SERVICE_ATE, self::SERVICE_AMS ], array_keys( array_filter( $answered ) ) ) );
		}

		return empty( $answered ) ? [] : [ self::SERVICE_ATE, self::SERVICE_AMS ];
	}

	public static function displayPhase() {
		if ( ! self::isCannotAccess() ) {
			return 'reconnecting';
		}

		return self::openedByCreate() && self::hasReadAnswered() ? 'reconnecting' : 'cannot-access';
	}

	public static function hasEscalated() {
		$state = self::read();

		if ( ! $state || empty( $state['first_failure_at'] ) ) {
			return false;
		}

		if ( self::isNoAnswer() ) {
			return false;
		}

		return ( time() - (int) $state['first_failure_at'] ) >= self::ESCALATE_AFTER_SECONDS;
	}

	public static function isCannotAccess() {
		$state = self::read();

		return (bool) $state && self::isNoAnswer() && (int) self::prop( 'failed_reattempts', $state, 0 ) >= 1;
	}

	public static function isSupportBlockDue() {
		$state = self::read();

		if ( ! $state || ! self::isNoAnswer() ) {
			return false;
		}

		return (int) self::prop( 'attempts', $state, 0 ) >= self::SUPPORT_AFTER_ATTEMPTS
			&& ( time() - (int) self::prop( 'first_failure_at', $state, time() ) ) >= self::SUPPORT_AFTER_SECONDS;
	}

	public static function secondsUntilProbe() {
		$state = self::read();

		if ( ! $state ) {
			return 0;
		}

		return max( 0, (int) self::prop( 'next_attempt_at', $state, 0 ) - time() );
	}

	public static function cronDueAt() {
		$state = self::read();

		if ( ! $state ) {
			return 0;
		}

		$due = (int) self::prop( 'cron_due_at', $state, 0 );

		return $due > 0 ? $due : (int) self::prop( 'next_attempt_at', $state, 0 );
	}

	public static function service() {
		return self::normaliseService( self::prop( 'service', self::read(), '' ) );
	}

	public static function host() {
		return (string) self::prop( 'host', self::read(), '' );
	}

	public static function hosts() {
		$hosts = self::prop( 'hosts', self::read(), [] );

		return is_array( $hosts ) ? array_map( 'strval', $hosts ) : [];
	}

	public static function clientState( $websiteUuid = '' ) {
		$state = self::read();
		$open  = (bool) $state && self::isNoAnswer() && self::isReconnecting();

		$supportBlock = null;
		if ( $open ) {
			$supportBlock = [
				'code'   => self::diagnosticCode( $websiteUuid ),
				'report' => self::debugReport( $websiteUuid ),
			];
		}

		return [
			'open'           => $open,
			'diagnosed'      => (bool) $state && ! self::isNoAnswer(),
			'service'        => $open ? self::service() : '',
			'host'           => $open ? self::host() : '',
			'hosts'          => $open ? self::hosts() : [],
			'attempts'       => $open ? (int) self::prop( 'attempts', $state, 0 ) : 0,
			'firstFailureAt' => $open ? (int) self::prop( 'first_failure_at', $state, 0 ) : 0,
			'lastAttemptAt'  => $open ? (int) self::prop( 'last_attempt_at', $state, 0 ) : 0,
			'retryIn'        => $open ? self::secondsUntilProbe() : 0,
			'phase'          => $open ? self::displayPhase() : 'reconnecting',
			'openedBy'       => $open ? ( self::openedByCreate() ? self::OPENED_BY_CREATE : self::OPENED_BY_OTHER ) : '',
			'supportBlock'   => $supportBlock,
			'supportDue'     => $open && self::isSupportBlockDue(),
			'supportHidden'  => null !== $supportBlock && self::isEscalationDismissed( $supportBlock['code'] ),
			'now'            => time(),
		];
	}

	private static function normaliseService( $service ) {
		$service = strtolower( (string) $service );

		return in_array( $service, [ self::SERVICE_ATE, self::SERVICE_AMS, self::SERVICE_BOTH ], true ) ? $service : '';
	}

	public static function get( $fresh = false ) {
		return $fresh ? self::readFresh() : self::read();
	}

	public static function diagnosticCode( $websiteUuid = '' ) {
		$state = self::read();

		if ( ! $state ) {
			return '';
		}

		$digest = strtoupper(
			substr(
				md5(
					implode(
						'|',
						[
							(string) self::prop( 'first_failure_at', $state, 0 ),
							(string) self::prop( 'last_http_status', $state, 0 ),
							(string) self::prop( 'last_error_key', $state, '' ),
							(string) self::prop( 'old_url', $state, '' ),
							(string) self::prop( 'new_url', $state, '' ),
						]
					)
				),
				0,
				4
			)
		);

		$uuidPart = strtoupper( substr( preg_replace( '/[^0-9a-zA-Z]/', '', (string) $websiteUuid ), 0, 4 ) );

		if ( $uuidPart === '' ) {
			$uuidPart = 'WPML';
		}

		return $uuidPart . '-' . ( self::isNoAnswer() ? 'N' : 'S' ) . '-' . $digest;
	}

	public static function debugReport( $websiteUuid = '' ) {
		$state = self::read();

		if ( ! $state ) {
			return '';
		}

		$lines = [
			'WPML reconnect diagnostics',
			'code: ' . self::diagnosticCode( $websiteUuid ),
			'condition: ' . ( self::isNoAnswer() ? 'no answer from the translation server' : 'AMS answered with a diagnosis' ),
			'opened by: ' . ( self::OPENED_BY_CREATE === self::prop( 'opened_by', $state, self::OPENED_BY_OTHER ) ? self::OPENED_BY_CREATE : self::OPENED_BY_OTHER ),
			'service: ' . ( self::service() !== '' ? self::service() : 'unknown' ),
			'host: ' . ( self::host() !== '' ? self::host() : 'unknown' ),
			'site uuid: ' . ( $websiteUuid !== '' ? $websiteUuid : 'unknown' ),
		];

		if ( self::isNoAnswer() ) {
			$lines[] = 'site url: ' . self::currentUrl();
		} else {
			$lines[] = 'url on record: ' . self::prop( 'old_url', $state, 'unknown' );
			$lines[] = 'url in use: ' . self::prop( 'new_url', $state, 'unknown' );
		}

		$lines = array_merge( $lines, [
			'first failure: ' . self::formatTime( (int) self::prop( 'first_failure_at', $state, 0 ) ),
			'attempts: ' . (int) self::prop( 'attempts', $state, 0 ),
			'next attempt: ' . self::formatTime( (int) self::prop( 'next_attempt_at', $state, 0 ) ),
			'attempt log:',
		] );

		$errors = self::prop( 'last_errors', $state, [] );

		if ( ! is_array( $errors ) || ! $errors ) {
			$lines[] = '  (none recorded)';
		} else {
			foreach ( $errors as $error ) {
				$lines[] = sprintf(
					'  %s  http=%s  %s',
					self::formatTime( (int) self::prop( 'at', $error, 0 ) ),
					self::prop( 'status', $error, 0 ),
					self::prop( 'key', $error, '' )
				);
			}
		}

		$unreadable = \WPML\TM\ATE\JobCreation\AteCreateReconciler::unreadableRecords();
		if ( $unreadable ) {
			$lines[] = 'unreadable ATE lookup answers:';
			foreach ( $unreadable as $jobId => $record ) {
				$lines[] = sprintf(
					'  job %d: %d time(s), first %s, latest %s, %s: %s',
					$jobId,
					$record['count'],
					self::formatTime( $record['first'] ),
					self::formatTime( $record['last'] ),
					'' !== $record['code'] ? $record['code'] : 'unknown',
					preg_replace( '/\s+/', ' ', $record['answer'] )
				);
			}
		}

		return implode( "\n", $lines );
	}

	public static function extractUrls( array $data ) {
		$oldUrl = Obj::pathOr( '', [ 'stored_fingerprint', 'wp_url' ], $data );

		$receivedFingerprint = isset( $data['received_fingerprint'] ) ? $data['received_fingerprint'] : [];
		$newUrl              = Obj::propOr(
			'',
			'wp_url',
			is_string( $receivedFingerprint ) ? json_decode( $receivedFingerprint ) : $receivedFingerprint
		);

		return [
			'old_url' => (string) $oldUrl,
			'new_url' => (string) $newUrl,
		];
	}

	public static function delayFor( $attempts ) {
		$schedule = self::BACKOFF_SECONDS;
		$index    = max( 0, min( (int) $attempts - 1, count( $schedule ) - 1 ) );
		$base     = $schedule[ $index ];

		$spread = (int) round( $base * self::JITTER_PERCENT / 100 );

		if ( $spread < 1 ) {
			return $base;
		}

		return $base + mt_rand( - $spread, $spread );
	}

	private static function read() {
		$option = get_option( self::OPTION, [] );

		if ( is_array( $option ) ) {
			return $option;
		}

		delete_option( self::OPTION );

		return [];
	}

	private static function readFresh() {
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( self::OPTION, 'options' );
		}

		$notoptions = function_exists( 'wp_cache_get' ) ? wp_cache_get( 'notoptions', 'options' ) : false;
		if ( is_array( $notoptions ) && isset( $notoptions[ self::OPTION ] ) && function_exists( 'wp_cache_set' ) ) {
			unset( $notoptions[ self::OPTION ] );
			wp_cache_set( 'notoptions', $notoptions, 'options' );
		}

		return self::read();
	}

	private static function currentUrl() {
		return make( FingerprintGenerator::class )->getClonedSiteUrl();
	}

	private static function prop( $key, $subject, $default ) {
		return is_array( $subject ) && isset( $subject[ $key ] ) ? $subject[ $key ] : $default;
	}

	private static function formatTime( $timestamp ) {
		return $timestamp ? gmdate( 'Y-m-d H:i:s', $timestamp ) . ' UTC' : 'unknown';
	}
}
