<?php

namespace WPML\TM\TranslationProxy;

class SendTuning {

	const TIME_BUDGET_SECONDS = 20;

	const SEND_CALL_TIMEOUT_SECONDS = 20;

	const MAX_ELEMENTS_PER_REQUEST = 200;

	const CONTINUATION_ROUND_CAP = 10;

	const PICKUP_PAGE_SIZE = 500;

	const APPLY_FAILURE_CAP = 3;

	const MAX_FAILURE_REASON_LENGTH = 1000;

	const APPLY_FAILURE_RETRY_AFTER_SECONDS = 3600;

	const APPLY_FAILURE_RETRY_MAX_SECONDS = 86400;

	const RECEIVE_CALL_TIMEOUT_SECONDS = 20;

	const RECEIVE_TIME_BUDGET_SECONDS = 15;

	public static function timeBudgetSeconds() {
		return (int) apply_filters( 'wpml_tp_send_time_budget', self::TIME_BUDGET_SECONDS );
	}

	public static function sendCallTimeoutSeconds() {
		return (int) apply_filters( 'wpml_tp_send_call_timeout', self::SEND_CALL_TIMEOUT_SECONDS );
	}

	public static function maxElementsPerRequest() {
		return (int) apply_filters( 'wpml_tp_send_max_elements', self::MAX_ELEMENTS_PER_REQUEST );
	}

	public static function continuationRoundCap() {
		return (int) apply_filters( 'wpml_tp_send_continuation_rounds', self::CONTINUATION_ROUND_CAP );
	}

	public static function pickupPageSize() {
		return (int) apply_filters( 'wpml_tp_pickup_page_size', self::PICKUP_PAGE_SIZE );
	}

	public static function applyFailureCap() {
		return (int) apply_filters( 'wpml_tp_apply_failure_cap', self::APPLY_FAILURE_CAP );
	}

	public static function applyFailureRetryAfterSeconds( $cycles = 0 ) {
		$base = (int) apply_filters(
			'wpml_tp_apply_failure_retry_after',
			self::APPLY_FAILURE_RETRY_AFTER_SECONDS,
			$cycles
		);

		if ( $base <= 0 ) {
			return 0;
		}

		$cycles = max( 0, (int) $cycles );
		$wait = $cycles >= 20 ? self::APPLY_FAILURE_RETRY_MAX_SECONDS : $base * ( 1 << $cycles );

		return (int) min( $wait, self::APPLY_FAILURE_RETRY_MAX_SECONDS );
	}

	public static function receiveCallTimeoutSeconds() {
		return (int) apply_filters( 'wpml_tp_receive_call_timeout', self::RECEIVE_CALL_TIMEOUT_SECONDS );
	}

	public static function receiveTimeBudgetSeconds() {
		return (int) apply_filters( 'wpml_tp_receive_time_budget', self::RECEIVE_TIME_BUDGET_SECONDS );
	}

	public static function isAmbiguousSendFailure( $message ) {
		return (bool) preg_match(
			'/curl error 28|timed[\s-]?out|operation timeout|timeout was reached/i',
			(string) $message
		);
	}

	public static function sanitizeFailureReason( $message ) {
		$message = (string) $message;

		$message = (string) preg_replace( '/ param: `.*?`(?= response: `)/s', ' param: `[omitted]`', $message );

		$message = (string) preg_replace( '/[[:cntrl:]]+/', ' ', $message );
		$message = trim( (string) preg_replace( '/\s{2,}/', ' ', $message ) );

		$max = self::MAX_FAILURE_REASON_LENGTH;
		if ( strlen( $message ) > $max ) {
			$message = substr( $message, 0, $max ) . ' …[truncated]';
		}

		return $message;
	}


}
