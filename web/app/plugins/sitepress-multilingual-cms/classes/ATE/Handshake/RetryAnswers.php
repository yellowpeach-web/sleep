<?php

namespace WPML\TM\ATE\Handshake;

class RetryAnswers {

	const RETRIED            = 'retried';
	const REFUSED            = 'refused';
	const IN_PROGRESS        = 'retry_in_progress';
	const NOT_FAILED_ON_SITE = 'not_failed_on_site';
	const FAILED             = 'failed';

	const REASON_NOT_ENOUGH = 'not_enough';

	const REASON_WAITING_PAYMENT = 'waiting_automatic_payment';

	const REASON_SPEND_CAP = 'site_spend_cap';

	const REASONS = [ self::REASON_NOT_ENOUGH, self::REASON_WAITING_PAYMENT, self::REASON_SPEND_CAP ];

	private $retried = 0;

	private $failed = 0;

	private $inProgress = 0;

	private $refused = [];

	public static function read( array $answer ) {
		$code = isset( $answer['code'] ) ? (int) $answer['code'] : 0;
		$body = isset( $answer['body'] ) && is_array( $answer['body'] ) ? $answer['body'] : [];

		if ( $code >= 200 && $code < 300 ) {
			return [ 'outcome' => self::RETRIED ];
		}

		if ( 402 === $code ) {
			$reason  = isset( $body['reason'] ) ? (string) $body['reason'] : '';
			$refusal = [
				'outcome' => self::REFUSED,
				'reason'  => in_array( $reason, self::REASONS, true ) ? $reason : self::REASON_NOT_ENOUGH,
				'words'   => isset( $body['words'] ) ? max( 0, (int) $body['words'] ) : 0,
				'credits' => isset( $body['credits'] ) && is_numeric( $body['credits'] ) ? $body['credits'] + 0 : 0,
			];

			if ( self::REASON_SPEND_CAP === $refusal['reason'] && isset( $body['cap_words'], $body['used_words'] ) ) {
				$refusal['cap_words']  = (int) $body['cap_words'];
				$refusal['used_words'] = (int) $body['used_words'];
			}

			return $refusal;
		}

		if ( 409 === $code && isset( $body['error'] ) && in_array( $body['error'], [ self::IN_PROGRESS, self::NOT_FAILED_ON_SITE ], true ) ) {
			return [ 'outcome' => (string) $body['error'] ];
		}

		return [ 'outcome' => self::FAILED ];
	}

	public function add( array $read ) {
		switch ( $read['outcome'] ) {
			case self::RETRIED:
				$this->retried++;
				return;
			case self::IN_PROGRESS:
				$this->inProgress++;
				return;
			case self::REFUSED:
				$reason = $read['reason'];

				if ( ! isset( $this->refused[ $reason ] ) ) {
					$this->refused[ $reason ] = [
						'jobs'  => 0,
						'words' => 0,
					];
				}

				$this->refused[ $reason ]['jobs']++;
				$this->refused[ $reason ]['words'] += (int) $read['words'];

				if ( isset( $read['cap_words'], $read['used_words'] ) ) {
					$left = max( 0, (int) $read['cap_words'] - (int) $read['used_words'] );

					$this->refused[ $reason ]['left'] = isset( $this->refused[ $reason ]['left'] ) ? min( $this->refused[ $reason ]['left'], $left ) : $left;
				}
				return;
			default:
				$this->failed++;
		}
	}

	public function summary() {
		$refused = [];

		foreach ( self::REASONS as $reason ) {
			if ( isset( $this->refused[ $reason ] ) ) {
				$refused[ $reason ] = $this->refused[ $reason ];
			}
		}

		return [
			'retried'     => $this->retried,
			'failed'      => $this->failed,
			'in_progress' => $this->inProgress,
			'refused'     => $refused,
		];
	}
}
