<?php

namespace WPML\TM\ATE\Handshake\Support;

use WPML\TM\ATE\Handshake\AteClient;

class SystemView {

	const TRANSIENT = 'wpml_ate_delivery_box_state';
	const TTL       = 60;

	const OK             = 'ok';
	const NO_RECORD      = 'no_record';
	const NOT_REGISTERED = 'not_registered';
	const REFUSED        = 'refused';
	const UNREACHABLE    = 'unreachable';

	private $ate;

	public function __construct( ?AteClient $ate = null ) {
		$this->ate = $ate ?: new AteClient();
	}

	public function read( $fresh = false ) {
		if ( ! $fresh ) {
			$kept = get_transient( self::TRANSIENT );

			if ( is_array( $kept ) && isset( $kept['status'] ) ) {
				return $kept;
			}
		}

		$view = self::fromAnswer( $this->ate->state() );

		set_transient( self::TRANSIENT, $view, self::TTL );

		return $view;
	}

	public static function fromAnswer( array $answer ) {
		$code = isset( $answer['code'] ) ? (int) $answer['code'] : 0;
		$body = isset( $answer['body'] ) && is_array( $answer['body'] ) ? $answer['body'] : [];

		if ( $code >= 200 && $code < 300 && $body ) {
			$status = self::OK;
		} elseif ( 404 === $code && isset( $body['error'] ) && 'no_handshake' === $body['error'] ) {
			$status = self::NO_RECORD;
		} elseif ( 0 === $code && isset( $answer['error'] ) && 'not_registered' === $answer['error'] ) {
			$status = self::NOT_REGISTERED;
		} elseif ( 403 === $code ) {
			$status = self::REFUSED;
		} else {
			$status = self::UNREACHABLE;
		}

		return [
			'status'  => $status,
			'state'   => self::OK === $status ? $body : [],
			'code'    => $code,
			'read_at' => time(),
		];
	}

	public static function forget() {
		delete_transient( self::TRANSIENT );
	}
}
