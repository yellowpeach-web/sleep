<?php

namespace WPML\Request;

final class Payload {

	const INVALID = 'wpml_invalid_request';

	const STATUS = 400;

	public static function listField( array $request, $field, $message = '' ) {
		if ( ! array_key_exists( $field, $request ) ) {
			return self::invalid( $field, 'the request carries no ' . $field, $message );
		}

		if ( ! is_array( $request[ $field ] ) ) {
			return self::invalid( $field, $field . ' must be a list, not a single value', $message );
		}

		if ( [] === $request[ $field ] ) {
			return self::invalid( $field, $field . ' is empty, so there is nothing to act on', $message );
		}

		return $request[ $field ];
	}

	public static function isRefusal( $value ) {
		return $value instanceof \WP_Error;
	}

	public static function invalid( $field, $reason, $message = '' ) {
		return new \WP_Error(
			self::INVALID,
			'' !== trim( (string) $message ) ? (string) $message : self::defaultMessage(),
			[
				'status' => self::STATUS,
				'params' => [ (string) $field => (string) $reason ],
			]
		);
	}

	public static function refuse( \WP_Error $error ) {
		$data = (array) $error->get_error_data();

		wp_send_json_error(
			[
				'code'    => $error->get_error_code(),
				'message' => $error->get_error_message(),
				'params'  => isset( $data['params'] ) ? $data['params'] : [],
			],
			isset( $data['status'] ) ? (int) $data['status'] : self::STATUS
		);
	}

	private static function defaultMessage() {
		/* translators: Shown when a request from a WPML admin screen did not carry what it needed, so nothing was done. */
		return __( 'This request was incomplete, so nothing was changed. Please reload the page and try again.', 'sitepress' );
	}
}
