<?php

class OTGS_Installer_Products_Feed_Problem {

	const KIND_CONNECTION = 'connection';
	const KIND_HTTP       = 'http';
	const KIND_BODY       = 'body';

	const BODY_EMPTY            = 'empty_body';
	const BODY_NOT_JSON         = 'not_json';
	const BODY_INVALID_DOCUMENT = 'invalid_document';

	const CODE_PREFIX = 'PRODUCTS';

	private $kind;

	private $detail;

	private $message;

	private function __construct( $kind, $detail, $message ) {
		$this->kind    = (string) $kind;
		$this->detail  = (string) $detail;
		$this->message = (string) $message;
	}

	public static function connection( $error ) {
		$code    = is_object( $error ) && method_exists( $error, 'get_error_code' ) ? (string) $error->get_error_code() : '';
		$message = is_object( $error ) && method_exists( $error, 'get_error_message' ) ? (string) $error->get_error_message() : '';

		return new self(
			self::KIND_CONNECTION,
			'' !== $code ? $code : 'unknown',
			'' !== $message ? $message : 'the request did not get an answer'
		);
	}

	public static function http( $status ) {
		$status = (int) $status;

		return new self(
			self::KIND_HTTP,
			$status > 0 ? (string) $status : 'no_status',
			$status > 0 ? sprintf( 'the server answered HTTP %d instead of the products list', $status ) : 'the server answered without an HTTP status'
		);
	}

	public static function body( $defect, $message = '' ) {
		return new self(
			self::KIND_BODY,
			'' !== (string) $defect ? $defect : self::BODY_INVALID_DOCUMENT,
			'' !== (string) $message ? $message : 'the server answered, but not with a products list'
		);
	}

	public function code() {
		return strtoupper( self::CODE_PREFIX . '-' . $this->kind . '-' . preg_replace( '/[^a-z0-9]+/i', '_', trim( $this->detail ) ) );
	}

	public function describe() {
		return $this->code() . ': ' . $this->message;
	}

	public function kind() {
		return $this->kind;
	}

	public function detail() {
		return $this->detail;
	}

	public function message() {
		return $this->message;
	}
}
