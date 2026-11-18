<?php

namespace WPML\Media\TextScan;

class ClassifierUnavailable extends \RuntimeException {

	const NO_ENDPOINT        = 'no_endpoint';
	const UNTRUSTED_ENDPOINT = 'untrusted_endpoint';
	const TRANSPORT          = 'transport';
	const HTTP_STATUS        = 'http_status';
	const BAD_BODY           = 'bad_body';
	const BLOCKED            = 'blocked';

	private $reason;

	private $host;

	public function __construct( $reason, $detail = '', $host = '' ) {
		$this->reason = (string) $reason;
		$this->host   = (string) $host;
		parent::__construct( $detail ? $this->reason . ': ' . $detail : $this->reason );
	}

	public static function noEndpoint() {
		return new self( self::NO_ENDPOINT );
	}

	public static function untrustedEndpoint() {
		return new self( self::UNTRUSTED_ENDPOINT );
	}

	public static function transport( $detail ) {
		return new self( self::TRANSPORT, (string) $detail );
	}

	public static function httpStatus( $status ) {
		return new self( self::HTTP_STATUS, (string) (int) $status );
	}

	public static function badBody() {
		return new self( self::BAD_BODY );
	}

	public static function blocked( $host ) {
		return new self( self::BLOCKED, '', (string) $host );
	}

	public function reason() {
		return $this->reason;
	}

	public function host() {
		return $this->host;
	}
}
