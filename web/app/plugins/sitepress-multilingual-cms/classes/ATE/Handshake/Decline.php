<?php

namespace WPML\TM\ATE\Handshake;

class Decline {

	const CANCELLED_ON_SITE = 'cancelled_on_site';
	const POST_DELETED      = 'post_deleted';
	const EDITED_BY_CLIENT  = 'edited_by_client';
	const SUPERSEDED        = 'superseded';
	const MISSING           = 'missing';
	const WRONG_ORIGINAL    = 'wrong_original';
	const APPLY_FAILED      = 'apply_failed';

	const REASONS = [
		self::CANCELLED_ON_SITE,
		self::POST_DELETED,
		self::EDITED_BY_CLIENT,
		self::SUPERSEDED,
		self::MISSING,
		self::WRONG_ORIGINAL,
		self::APPLY_FAILED,
	];

	private $ate;

	private $marks;

	private $lastCode = 0;

	public function __construct( ?AteClient $ate = null, ?Marks $marks = null ) {
		$this->ate   = $ate ?: new AteClient();
		$this->marks = $marks ?: new Marks();
	}

	public static function givenUp( $wpmlJobId, $ateJobId ) {
		return ( new self() )->send( (int) $ateJobId, (int) $wpmlJobId, self::APPLY_FAILED );
	}

	public function send( $ateJobId, $wpmlJobId, $reason ) {
		$code = $this->sendForCode( $ateJobId, $wpmlJobId, $reason );

		return $code >= 200 && $code < 300;
	}

	public function sendForCode( $ateJobId, $wpmlJobId, $reason ) {
		$this->lastCode = 0;
		$reason         = in_array( $reason, self::REASONS, true ) ? $reason : self::MISSING;
		$body   = [
			'job_id'      => (int) $ateJobId,
			'reason'      => $reason,
			'wpml_job_id' => (int) $wpmlJobId,
		];

		Declaration::begin();

		try {
			$answer = $this->ate->decline( $body );

			$this->lastCode = (int) $answer['code'];

			$ok = $answer['code'] >= 200 && $answer['code'] < 300;

			if ( $ok && (int) $wpmlJobId > 0 ) {
				$this->marks->set( (int) $wpmlJobId, (int) $ateJobId, Marks::DECLINED );
			}

			Log::write(
				'declined',
				[
					'reason'         => $reason,
					'ok'             => $ok,
					'http_code'      => $answer['code'],
					'error'          => $answer['error'],
					'words_returned' => is_array( $answer['body'] ) && isset( $answer['body']['words_returned'] ) ? (int) $answer['body']['words_returned'] : null,
				],
				(int) $wpmlJobId,
				(int) $ateJobId
			);

			return $this->lastCode;
		} finally {
			Declaration::end();
		}
	}

	public static function isDecided( $code ) {
		$code = (int) $code;

		return ( $code >= 200 && $code < 300 ) || ( $code >= 400 && $code < 500 && ! in_array( $code, [ 408, 429 ], true ) );
	}

	public static function reasonFor( $outcome ) {
		$map = [
			\WPML\TM\ATE\Receive\SingleJobDelivery::CANCELLED      => self::CANCELLED_ON_SITE,
			\WPML\TM\ATE\Receive\SingleJobDelivery::JOB_MISSING    => self::MISSING,
			\WPML\TM\ATE\Receive\SingleJobDelivery::CLIENT_EDITS   => self::EDITED_BY_CLIENT,
			\WPML\TM\ATE\Receive\SingleJobDelivery::SUPERSEDED     => self::SUPERSEDED,
			\WPML\TM\ATE\Receive\SingleJobDelivery::CLOSED         => self::SUPERSEDED,
			\WPML\TM\ATE\Receive\SingleJobDelivery::WRONG_ORIGINAL => self::WRONG_ORIGINAL,
		];

		return isset( $map[ $outcome ] ) ? $map[ $outcome ] : null;
	}
}
