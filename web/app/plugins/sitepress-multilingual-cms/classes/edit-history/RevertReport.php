<?php

namespace WPML\EditHistory;

class RevertReport {

	const REVERTED = 'reverted';

	const LOG = 'edit_history_revert_reported';

	private $send;

	private $jobLog;

	public function __construct( callable $send, callable $jobLog ) {
		$this->send   = $send;
		$this->jobLog = $jobLog;
	}

	public function send( int $ateJobId, $deliveryId = null ) {
		$error = null;
		if ( $ateJobId <= 0 ) {
			$error = 'the item names no ATE job';
		} else {
			try {
				$answer = ( $this->send )( self::item( $ateJobId, $deliveryId ) );
				$error  = $answer instanceof \WP_Error ? $answer->get_error_message() : null;
			} catch ( \Throwable $thrown ) {
				$error = $thrown->getMessage();
			}
		}

		( $this->jobLog )(
			self::LOG,
			[
				'ate_job_id' => $ateJobId,
				'sent'       => null === $error,
				'reason'     => $error,
			]
		);
	}

	private static function item( int $ateJobId, $deliveryId ): array {
		return [
			'job_id'      => $ateJobId,
			'delivery_id' => $deliveryId > 0 ? (int) $deliveryId : null,
			'event'       => self::REVERTED,
			'reason'      => null,
		];
	}
}
