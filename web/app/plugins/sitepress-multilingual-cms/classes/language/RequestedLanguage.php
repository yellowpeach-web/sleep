<?php

namespace WPML\Language;

use WPML\Core\Security\ExecutionContext\ExecutionContextHolder;

final class RequestedLanguage {

	const SCOPE_VIEWER = 'viewer';

	const SCOPE_CONFIGURED = 'configured';

	const ALL = 'all';

	const MAX_LENGTH = 32;

	const SYNTAX = '/^[a-z0-9]{2,}(?:[-_][a-z0-9]+)*$/';

	private $resolution;

	private $request;

	private $isTrustedContext;

	private static $override = null;

	public function __construct( $resolution, $request, ?callable $isTrustedContext = null ) {
		$this->resolution = is_object( $resolution )
			&& method_exists( $resolution, 'get_active_language_codes' )
			&& method_exists( $resolution, 'is_language_hidden' )
			? $resolution : null;
		$this->request    = is_object( $request ) && method_exists( $request, 'show_hidden' ) ? $request : null;

		$this->isTrustedContext = $isTrustedContext ?: [ ExecutionContextHolder::class, 'isTrusted' ];
	}

	public static function fromGlobals() {
		if ( null !== self::$override ) {
			return self::$override;
		}

		global $wpml_language_resolution, $wpml_request_handler;

		return new self( $wpml_language_resolution, $wpml_request_handler );
	}

	public static function useForTesting( ?self $policy = null ) {
		self::$override = $policy;
	}


	public static function validate( $raw, $scope = self::SCOPE_VIEWER, $allowAll = false ) {
		return self::fromGlobals()->validateIn( $raw, (string) $scope, (bool) $allowAll );
	}

	public static function forViewer( $raw, $allowAll = false ) {
		return self::fromGlobals()->validateIn( $raw, self::SCOPE_VIEWER, (bool) $allowAll );
	}

	public static function forPrivileged( $raw, $allowAll = false ) {
		$policy = self::fromGlobals();

		return $policy->validateIn(
			$raw,
			$policy->isPrivilegedContext() ? self::SCOPE_CONFIGURED : self::SCOPE_VIEWER,
			(bool) $allowAll
		);
	}

	public static function configured() {
		return self::fromGlobals()->configuredCodes();
	}

	public static function isSyntacticallyValid( $raw ) {
		return null !== self::canonical( $raw );
	}

	public static function canonical( $raw ) {
		if ( ! is_string( $raw ) && ! is_int( $raw ) ) {
			return null;
		}

		$code = strtolower( trim( (string) $raw ) );

		if ( '' === $code || strlen( $code ) > self::MAX_LENGTH || ! preg_match( self::SYNTAX, $code ) ) {
			return null;
		}

		return $code;
	}


	public function validateIn( $raw, $scope, $allowAll ) {
		$code = self::canonical( $raw );
		if ( null === $code ) {
			return null;
		}

		if ( self::ALL === $code ) {
			return $allowAll && self::SCOPE_CONFIGURED === $scope ? self::ALL : null;
		}

		if ( ! $this->isConfigured( $code ) ) {
			return null;
		}

		if ( self::SCOPE_CONFIGURED === $scope ) {
			return $code;
		}

		return $this->isVisibleToViewer( $code ) ? $code : null;
	}

	public function isConfigured( $code ) {
		return in_array( (string) $code, $this->configuredCodes(), true );
	}

	public function configuredCodes() {
		if ( null === $this->resolution ) {
			return [];
		}

		return array_values( array_map( 'strval', (array) $this->resolution->get_active_language_codes() ) );
	}

	public function isHidden( $code ) {
		return null !== $this->resolution && (bool) $this->resolution->is_language_hidden( (string) $code );
	}

	public function isVisibleToViewer( $code ) {
		if ( ! $this->isConfigured( $code ) ) {
			return false;
		}

		return ! $this->isHidden( $code ) || $this->viewerMaySeeHidden();
	}

	public function isPrivilegedContext() {
		return (bool) call_user_func( $this->isTrustedContext ) || $this->viewerMaySeeHidden();
	}

	private function viewerMaySeeHidden() {
		return null !== $this->request && (bool) $this->request->show_hidden();
	}
}
