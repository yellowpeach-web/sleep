<?php

namespace WPML\TM\ATE\Handshake\Corrections;

use WPML\TM\ATE\Handshake\AteClient;

class Client extends AteClient {

	const ENDPOINT_OFFERED = '/api/wpml/handshake/corrections';
	const ENDPOINT_DECIDE  = '/api/wpml/handshake/corrections/decide';

	const READ_TIMEOUT = 5;

	const DECIDE_TIMEOUT = 30;

	public function offered() {
		$answer = $this->request( 'GET', self::ENDPOINT_OFFERED, null, self::READ_TIMEOUT );
		$error  = self::errorOf( $answer );

		if ( '' !== $error ) {
			return [
				'ok'    => false,
				'rows'  => [],
				'error' => $error,
			];
		}

		$rows = self::rows( $answer['body'] );

		if ( null === $rows ) {
			return [
				'ok'    => false,
				'rows'  => [],
				'error' => 'unexpected_answer',
			];
		}

		return [
			'ok'    => true,
			'rows'  => $rows,
			'error' => '',
		];
	}

	public function decide( array $accept, array $decline ) {
		$accept  = self::ids( $accept );
		$decline = array_values( array_diff( self::ids( $decline ), $accept ) );

		if ( ! $accept && ! $decline ) {
			return [
				'ok'    => true,
				'code'  => 0,
				'body'  => null,
				'error' => '',
			];
		}

		$answer = $this->request(
			'POST',
			self::ENDPOINT_DECIDE,
			[
				'accept'  => $accept,
				'decline' => $decline,
			],
			self::DECIDE_TIMEOUT
		);
		$error  = self::errorOf( $answer );

		return [
			'ok'    => '' === $error,
			'code'  => (int) $answer['code'],
			'body'  => $answer['body'],
			'error' => $error,
		];
	}

	public static function rows( $body ) {
		if ( is_array( $body ) && isset( $body['corrections'] ) ) {
			$body = $body['corrections'];
		}

		if ( ! is_array( $body ) || ( $body && array_keys( $body ) !== range( 0, count( $body ) - 1 ) ) ) {
			return null;
		}

		$rows = [];

		foreach ( $body as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['ate_id'] ) || (int) $row['ate_id'] < 1 ) {
				continue;
			}

			$rows[] = [
				'ate_id'       => (int) $row['ate_id'],
				'rid'          => isset( $row['rid'] ) && is_scalar( $row['rid'] ) ? (string) $row['rid'] : '',
				'language'     => isset( $row['language'] ) && is_scalar( $row['language'] ) ? (string) $row['language'] : '',
				'batch_id'     => isset( $row['batch_id'] ) && is_scalar( $row['batch_id'] ) ? (string) $row['batch_id'] : '',
				'reason'       => isset( $row['reason'] ) && is_scalar( $row['reason'] ) ? trim( (string) $row['reason'] ) : '',
				'corrected_at' => isset( $row['corrected_at'] ) ? self::time( $row['corrected_at'] ) : 0,
			];
		}

		return $rows;
	}

	private static function errorOf( array $answer ) {
		if ( '' !== (string) $answer['error'] ) {
			return (string) $answer['error'];
		}

		$code = (int) $answer['code'];

		return $code >= 200 && $code < 300 ? '' : 'http_' . $code;
	}

	private static function time( $value ) {
		if ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) {
			return (int) $value;
		}

		$time = is_string( $value ) ? strtotime( $value ) : false;

		return false === $time ? 0 : (int) $time;
	}

	private static function ids( array $ids ) {
		return array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
	}
}
