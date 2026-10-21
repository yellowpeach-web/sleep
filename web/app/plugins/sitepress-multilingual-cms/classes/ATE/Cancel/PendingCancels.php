<?php

namespace WPML\TM\ATE\Cancel;

use WPML\TM\ATE\ClonedSites\ApiCommunication;
use WPML\TM\Jobs\JobLog;
use function WPML\Container\make;

class PendingCancels {

	const OPTION = 'wpml_tm_ate_pending_cancels';

	const CAP = 200;

	const ADMIN_RETRY_INTERVAL = 60;

	const REPLAY_TIMEOUT = 10;

	const REPLAY_CHUNK = 50;

	const MAX_AGE = 2592000;

	const NOT_DIALLED_CODES = [ 'auth_error', ApiCommunication::RECONNECTING_ERROR_CODE ];

	const ENDPOINT_CANCEL = 'cancel';

	const ENDPOINT_HIDE = 'hide';

	public static function isUnanswered( $response ) {
		if ( is_wp_error( $response ) ) {
			return \WPML_TM_ATE_API::isTransportFailure( $response )
				|| in_array( $response->get_error_code(), self::NOT_DIALLED_CODES, true );
		}

		return ! is_array( $response ) && ! is_object( $response );
	}

	private static function stopsThePass( $response ) {
		return ! is_wp_error( $response )
			|| ApiCommunication::RECONNECTING_ERROR_CODE !== $response->get_error_code();
	}

	public static function remember( array $ateJobIds, $endpoint, $reason, $force = false, array $context = [] ) {
		$ateJobIds = self::ids( $ateJobIds );
		if ( ! $ateJobIds ) {
			return;
		}

		$stored = self::read();
		foreach ( $ateJobIds as $ateJobId ) {
			$stored['jobs'][ (string) $ateJobId ] = [
				'endpoint'    => self::ENDPOINT_HIDE === $endpoint ? self::ENDPOINT_HIDE : self::ENDPOINT_CANCEL,
				'reason'      => (string) $reason,
				'force'       => (bool) $force,
				'at'          => time(),
				'attempts'    => 0,
				'wpml_job_id' => isset( $context['wpml_job_id'] ) ? (int) $context['wpml_job_id'] : 0,
			];
		}

		$excess  = count( $stored['jobs'] ) - self::CAP;
		$dropped = $excess > 0 ? array_map( 'intval', array_keys( array_slice( $stored['jobs'], 0, $excess, true ) ) ) : [];
		foreach ( $dropped as $ateJobId ) {
			unset( $stored['jobs'][ (string) $ateJobId ] );
		}

		self::write( $stored );

		JobLog::add( 'ate_cancel_pending', [
			'ate_job_ids' => $ateJobIds,
			'endpoint'    => $endpoint,
			'reason'      => $reason,
			'pending'     => count( $stored['jobs'] ),
		] );

		if ( $dropped ) {
			JobLog::addError( 'ate_cancel_pending_dropped', [
				'ate_job_ids' => $dropped,
				'cap'         => self::CAP,
			] );
		}
	}

	public static function forget( array $ateJobIds ) {
		$ateJobIds = self::ids( $ateJobIds );
		if ( ! $ateJobIds ) {
			return;
		}

		$stored = self::read();
		foreach ( $ateJobIds as $ateJobId ) {
			unset( $stored['jobs'][ (string) $ateJobId ] );
		}
		self::write( $stored );
	}

	public static function pending() {
		return array_map( 'intval', array_keys( self::read()['jobs'] ) );
	}

	public static function retry( \WPML_TM_ATE_API $api ) {
		$stored = self::read();
		$result = [ 'sent' => [], 'kept' => [] ];

		if ( ! $stored['jobs'] ) {
			return $result;
		}

		$now = time();

		if ( $now - (int) $stored['last_try'] < self::ADMIN_RETRY_INTERVAL ) {
			$result['kept'] = array_map( 'intval', array_keys( $stored['jobs'] ) );

			return $result;
		}

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

		$stored['last_try'] = $now;
		self::write( $stored );

		if ( ! $stored['jobs'] ) {
			return $result;
		}

		$groups = [];
		foreach ( $stored['jobs'] as $ateJobId => $entry ) {
			$key              = $entry['endpoint'] . '|' . $entry['reason'] . '|' . ( $entry['force'] ? 1 : 0 );
			$groups[ $key ][] = (int) $ateJobId;
		}

		$unreachable = false;
		$dialled     = [];
		foreach ( $groups as $key => $ateJobIds ) {
			if ( $unreachable ) {
				$result['kept'] = array_merge( $result['kept'], $ateJobIds );
				continue;
			}

			list( $endpoint, $reason, $force ) = explode( '|', $key );

			foreach ( array_chunk( $ateJobIds, self::REPLAY_CHUNK ) as $chunk ) {
				if ( $unreachable ) {
					$result['kept'] = array_merge( $result['kept'], $chunk );
					continue;
				}

				$dialled     = array_merge( $dialled, $chunk );
				$requestArgs = [ 'timeout' => self::REPLAY_TIMEOUT ];

				try {
					$response = self::ENDPOINT_HIDE === $endpoint
						? $api->hideJobs( $chunk, (bool) $force, $reason, $requestArgs )
						: $api->cancelJobs( $chunk, false, $reason, $requestArgs );
				} catch ( \Throwable $e ) {
					$response = null;
				}

				if ( self::isUnanswered( $response ) ) {
					$unreachable    = self::stopsThePass( $response );
					$result['kept'] = array_merge( $result['kept'], $chunk );
					JobLog::addError( 'ate_cancel_retry_unanswered', [
						'ate_job_ids' => $chunk,
						'endpoint'    => $endpoint,
						'error'       => is_wp_error( $response ) ? $response->get_error_message() : null,
						'code'        => is_wp_error( $response ) ? $response->get_error_code() : null,
					] );
					continue;
				}

				$result['sent'] = array_merge( $result['sent'], $chunk );
				JobLog::add( 'ate_cancel_retried', [
					'ate_job_ids' => $chunk,
					'endpoint'    => $endpoint,
					'reason'      => $reason,
					'refused'     => is_wp_error( $response ) ? $response->get_error_code() : null,
				] );
			}
		}

		self::settle( $result['sent'], $dialled );

		return $result;
	}

	private static function settle( array $sent, array $dialled ) {
		if ( ! $sent && ! $dialled ) {
			return;
		}

		$stored = self::read();
		foreach ( $sent as $ateJobId ) {
			unset( $stored['jobs'][ (string) $ateJobId ] );
		}
		foreach ( $dialled as $ateJobId ) {
			if ( isset( $stored['jobs'][ (string) $ateJobId ] ) ) {
				$entry             = $stored['jobs'][ (string) $ateJobId ];
				$entry['attempts'] = ( isset( $entry['attempts'] ) ? (int) $entry['attempts'] : 0 ) + 1;

				$stored['jobs'][ (string) $ateJobId ] = $entry;
			}
		}
		self::write( $stored );
	}

	public static function retryOnAdminRequest() {
		$stored = self::read();

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

	private static function read() {
		$stored = get_option( self::OPTION, [] );

		return [
			'jobs'     => is_array( $stored ) && isset( $stored['jobs'] ) && is_array( $stored['jobs'] ) ? $stored['jobs'] : [],
			'last_try' => is_array( $stored ) && isset( $stored['last_try'] ) ? (int) $stored['last_try'] : 0,
		];
	}

	private static function write( array $stored ) {
		if ( ! $stored['jobs'] ) {
			delete_option( self::OPTION );

			return;
		}

		update_option( self::OPTION, $stored, false );
	}
}
