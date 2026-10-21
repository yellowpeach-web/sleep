<?php

namespace WPML\Request\Policy;

final class Authenticity {

	const ACTION_NONCE   = 'action-nonce';
	const REST_NONCE     = 'rest-nonce';
	const REST_TRANSPORT = 'rest-transport';
	const VERIFIER       = 'verifier';
	const NONE           = 'none';

	const DEFAULT_FIELDS = [ '_wpnonce', '_ajax_nonce' ];

	private $kind;

	private $action = '';

	private $fields = [];

	private $verifier = null;

	private $description = '';

	private function __construct( $kind ) {
		$this->kind = $kind;
	}

	public static function actionNonce( $action, $fields = self::DEFAULT_FIELDS ) {
		$self              = new self( self::ACTION_NONCE );
		$self->action      = (string) $action;
		$self->fields      = array_values( array_filter( array_map( 'strval', (array) $fields ), 'strlen' ) );
		$self->description = 'nonce "' . $self->action . '" in ' . implode( '|', $self->fields );

		return $self;
	}

	public static function wpmlActionNonce( $action ) {
		$action = (string) $action;

		return self::verifier(
			function () use ( $action ) {
				$iclNonce = self::requestValue( '_icl_nonce' );
				if ( '' !== $iclNonce ) {
					return false !== wp_verify_nonce( $iclNonce, $action . '_nonce' );
				}
				$nonce = self::requestValue( 'nonce' );

				return '' !== $nonce && false !== wp_verify_nonce( $nonce, $action );
			},
			'nonce "' . $action . '_nonce" in _icl_nonce or "' . $action . '" in nonce'
		);
	}

	public static function restNonce() {
		$self              = new self( self::REST_NONCE );
		$self->action      = 'wp_rest';
		$self->fields      = [ '_wpnonce' ];
		$self->description = 'wp_rest nonce (X-WP-Nonce header or _wpnonce)';

		return $self;
	}

	public static function restTransport() {
		$self              = new self( self::REST_TRANSPORT );
		$self->description = 'WordPress REST cookie authentication (rest_cookie_check_errors) verified X-WP-Nonce';

		return $self;
	}

	public static function verifier( callable $verifier, $description ) {
		$self              = new self( self::VERIFIER );
		$self->verifier    = $verifier;
		$self->description = (string) $description;

		return $self;
	}

	public static function none( $reason ) {
		$self              = new self( self::NONE );
		$self->description = (string) $reason;

		return $self;
	}

	public function verify( ...$args ) {
		try {
			switch ( $this->kind ) {
				case self::ACTION_NONCE:
					return '' !== $this->action && $this->nonceInFields( $this->fields, $this->action );

				case self::REST_NONCE:
					return $this->restNonceValid( $args );

				case self::REST_TRANSPORT:
				case self::NONE:
					return true;

				case self::VERIFIER:
					return true === call_user_func_array( $this->verifier, $args );
			}
		} catch ( \Throwable $e ) {
			return false;
		}

		return false;
	}

	public function kind() {
		return $this->kind;
	}

	public function action() {
		return $this->action;
	}

	public function fields() {
		return $this->fields;
	}

	public function description() {
		return $this->description;
	}

	public function isNone() {
		return self::NONE === $this->kind;
	}

	private function nonceInFields( array $fields, $action ) {
		foreach ( $fields as $field ) {
			$value = self::requestValue( $field );
			if ( '' !== $value && false !== wp_verify_nonce( $value, $action ) ) {
				return true;
			}
		}

		return false;
	}

	private function restNonceValid( array $args ) {
		$nonce = '';

		$request = isset( $args[0] ) && is_object( $args[0] ) && is_callable( [ $args[0], 'get_header' ] ) ? $args[0] : null;
		if ( $request ) {
			$nonce = (string) $request->get_header( 'x_wp_nonce' );
			if ( '' === $nonce && is_callable( [ $request, 'get_param' ] ) ) {
				$nonce = (string) $request->get_param( '_wpnonce' );
			}
		}
		if ( '' === $nonce && isset( $_SERVER['HTTP_X_WP_NONCE'] ) && is_string( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) );
		}
		if ( '' === $nonce ) {
			$nonce = self::requestValue( '_wpnonce' );
		}

		return '' !== $nonce && false !== wp_verify_nonce( $nonce, 'wp_rest' );
	}

	private static function requestValue( $field ) {
		if ( ! isset( $_REQUEST[ $field ] ) || ! is_scalar( $_REQUEST[ $field ] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( (string) $_REQUEST[ $field ] ) );
	}
}
