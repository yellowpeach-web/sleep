<?php

namespace WPML\Request\Policy;

final class Policy {

	const CAPABILITY    = 'capability';
	const AUTHENTICATED = 'authenticated';
	const AUTHORIZE     = 'authorize';
	const PUBLIC_ACCESS = 'public';
	const MACHINE       = 'machine';
	const LISTENER      = 'listener';

	const ADMINISTRATOR = 'manage_options';

	const DENIED = 'wpml_request_denied';

	private $kind;

	private $authenticity;

	private $capabilities = [];

	private $decider = null;

	private $reason = '';

	private function __construct( $kind, Authenticity $authenticity ) {
		$this->kind         = $kind;
		$this->authenticity = $authenticity;
	}

	public static function capability( $capabilities, Authenticity $authenticity ) {
		$self               = new self( self::CAPABILITY, $authenticity );
		$self->capabilities = array_values(
			array_filter(
				array_map( 'strval', (array) $capabilities ),
				function ( $capability ) {
					return '' !== $capability;
				}
			)
		);

		return $self;
	}

	public static function authenticated( Authenticity $authenticity, $reason ) {
		$self         = new self( self::AUTHENTICATED, $authenticity );
		$self->reason = (string) $reason;

		return $self;
	}

	public static function authorize( callable $decider, Authenticity $authenticity, $description ) {
		$self          = new self( self::AUTHORIZE, $authenticity );
		$self->decider = $decider;
		$self->reason  = (string) $description;

		return $self;
	}

	public static function publicAccess( $reason, ?Authenticity $authenticity = null ) {
		$self         = new self( self::PUBLIC_ACCESS, $authenticity ?: Authenticity::none( 'public operation' ) );
		$self->reason = (string) $reason;

		return $self;
	}

	public static function machine( callable $verifier, $reason ) {
		$self          = new self( self::MACHINE, Authenticity::verifier( $verifier, 'machine credential: ' . $reason ) );
		$self->decider = $verifier;
		$self->reason  = (string) $reason;

		return $self;
	}

	public static function listener( $reason ) {
		$self         = new self( self::LISTENER, Authenticity::none( 'host action enforces its own policy' ) );
		$self->reason = (string) $reason;

		return $self;
	}

	public function permits( ...$args ) {
		return true === $this->evaluate( ...$args );
	}

	public function evaluate( ...$args ) {
		try {
			if ( self::LISTENER === $this->kind ) {
				return true;
			}

			if ( self::MACHINE !== $this->kind && ! $this->authenticity->verify( ...$args ) ) {
				return 'request authenticity check failed';
			}

			switch ( $this->kind ) {
				case self::PUBLIC_ACCESS:
					return true;

				case self::AUTHENTICATED:
					return is_user_logged_in() ? true : 'authentication required';

				case self::CAPABILITY:
					return $this->currentUserCanAny() ? true : 'insufficient capabilities';

				case self::AUTHORIZE:
				case self::MACHINE:
					$verdict = call_user_func_array( $this->decider, $args );
					if ( true === $verdict ) {
						return true;
					}

					return self::isWpError( $verdict ) ? $verdict : 'denied by policy';
			}
		} catch ( \Throwable $e ) {
			return 'policy evaluation failed: ' . get_class( $e );
		}

		return 'unknown policy';
	}

	public function kind() {
		return $this->kind;
	}

	public function authenticity() {
		return $this->authenticity;
	}

	public function capabilities() {
		return $this->capabilities;
	}

	public function reason() {
		return $this->reason;
	}

	public function allowsAnonymous() {
		return in_array( $this->kind, [ self::PUBLIC_ACCESS, self::MACHINE, self::LISTENER ], true );
	}

	public function isListener() {
		return self::LISTENER === $this->kind;
	}

	public function problems() {
		$problems = [];

		if ( self::CAPABILITY === $this->kind && [] === $this->capabilities ) {
			$problems[] = 'capability policy names no capability (would always deny)';
		}
		if ( in_array( $this->kind, [ self::PUBLIC_ACCESS, self::LISTENER, self::AUTHENTICATED, self::AUTHORIZE, self::MACHINE ], true ) && '' === trim( $this->reason ) ) {
			$problems[] = $this->kind . ' policy carries no reason/description';
		}
		if ( $this->authenticity->isNone() && '' === trim( $this->authenticity->description() ) ) {
			$problems[] = 'no request-authenticity requirement and no reason for it';
		}

		return $problems;
	}

	public function describe() {
		switch ( $this->kind ) {
			case self::CAPABILITY:
				$what = 'capability(' . implode( '|', $this->capabilities ) . ')';
				break;
			case self::PUBLIC_ACCESS:
				$what = 'Public(' . $this->reason . ')';
				break;
			case self::LISTENER:
				$what = 'Listener(' . $this->reason . ')';
				break;
			default:
				$what = $this->kind . '(' . $this->reason . ')';
		}

		return $what . ' + ' . $this->authenticity->description();
	}

	private function currentUserCanAny() {
		if ( [] === $this->capabilities ) {
			return false;
		}
		if ( current_user_can( self::ADMINISTRATOR ) ) {
			return true;
		}
		foreach ( $this->capabilities as $capability ) {
			if ( current_user_can( $capability ) ) {
				return true;
			}
		}

		return false;
	}

	private static function isWpError( $value ) {
		return is_object( $value ) && is_a( $value, 'WP_Error' );
	}
}
