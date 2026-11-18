<?php

namespace WPML\TM\ATE\JobCreation;

use WPML\Core\Component\Translation\Application\Service\TranslationService;
use WPML\TM\ATE\ClonedSites\ApiCommunication;
use WPML\TM\ATE\ClonedSites\ReconnectState;
use WPML\TM\ATE\Retry\Trigger;

class CreateFailureRecord {

	const OPTION_PREFIX = 'wpml_tm_ate_create_failure_';

	const KIND_CONNECTION = 'connection';

	const KIND_REFUSED = 'refused';

	const KIND_ERROR = 'error';

	const KIND_WORD_COUNT = 'word_count';

	const KIND_PRICES = 'prices';

	const MESSAGE_LENGTH = 500;

	public static function isGated( $error ) {
		if ( ! $error instanceof \WP_Error || ApiCommunication::RECONNECTING_ERROR_CODE !== $error->get_error_code() ) {
			return false;
		}

		$data = $error->get_error_data();

		return is_array( $data ) && ! empty( $data[ ApiCommunication::GATED_DATA_KEY ] );
	}

	public static function kindOf( \WP_Error $error ) {
		$code = $error->get_error_code();

		if ( is_numeric( $code ) ) {
			$status = (int) $code;

			return $status >= 400 && $status < 500 && ! in_array( $status, AteCreateReconciler::NO_ANSWER_HTTP_STATUSES, true )
				? self::KIND_REFUSED
				: self::KIND_CONNECTION;
		}

		return self::KIND_CONNECTION;
	}

	public static function messageOf( \WP_Error $error ) {
		$message = trim( (string) $error->get_error_message() );

		return '' !== $message ? $message : (string) $error->get_error_code();
	}

	public static function write( $jobId, $kind, $message ) {
		update_option(
			self::OPTION_PREFIX . (int) $jobId,
			[
				'kind'    => (string) $kind,
				'message' => substr( (string) $message, 0, self::MESSAGE_LENGTH ),
				'at'      => time(),
			],
			false
		);
	}

	public static function read( $jobId ) {
		$jobId    = (int) $jobId;
		$failures = get_option( 'wpml_words_to_translate_failures', [] );

		if ( is_array( $failures ) && isset( $failures[ $jobId ] ) && is_array( $failures[ $jobId ] ) ) {
			$reason = isset( $failures[ $jobId ]['reason'] ) ? $failures[ $jobId ]['reason'] : '';

			return [
				'kind'    => TranslationService::PRICES_UNAVAILABLE === $reason ? self::KIND_PRICES : self::KIND_WORD_COUNT,
				'message' => isset( $failures[ $jobId ]['message'] ) ? (string) $failures[ $jobId ]['message'] : '',
			];
		}

		$record = get_option( self::OPTION_PREFIX . $jobId, false );

		if ( ! is_array( $record ) || empty( $record['kind'] ) ) {
			return null;
		}

		return [
			'kind'    => (string) $record['kind'],
			'message' => isset( $record['message'] ) ? (string) $record['message'] : '',
		];
	}

	public static function forget( $jobId ) {
		delete_option( self::OPTION_PREFIX . (int) $jobId );
	}

	public static function secondsUntilNextTry() {
		return max( ( new Trigger() )->secondsUntilNextRetry(), ReconnectState::secondsUntilProbe() );
	}
}
