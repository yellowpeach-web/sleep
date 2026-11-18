<?php

namespace WPML\TM\ATE\Handshake;

class Signature {

	const KIND_WAKE  = 'wake';
	const KIND_DRIVE = 'drive';

	const ERROR_CODE = 'handshake_signature';

	const MAX_SKEW  = 300;
	const NONCE_TTL = 600;

	const HEADER_REQUEST_ID = 'X-ATE-Request-Id';
	const HEADER_SITE       = 'X-ATE-Handshake-Site';
	const HEADER_TIMESTAMP  = 'X-ATE-Handshake-Timestamp';
	const HEADER_NONCE      = 'X-ATE-Handshake-Nonce';
	const HEADER_SIGNATURE  = 'X-ATE-Handshake-Signature';

	private static $verdicts = [];

	private $siteId;

	private $secret;

	public function __construct( $siteId = null, $secret = null ) {
		$this->siteId = $siteId ?: function () {
			return (string) ( new \WPML_TM_ATE_Authentication() )->get_site_id();
		};
		$this->secret = $secret ?: function () {
			$data = get_option( \WPML_TM_ATE_Authentication::AMS_DATA_KEY, [] );

			return is_array( $data ) && isset( $data['secret'] ) ? (string) $data['secret'] : '';
		};
	}

	public static function verifyWake( $request ) {
		return ( new self() )->verify( $request, self::KIND_WAKE );
	}

	public static function verifyDrive( $request ) {
		return ( new self() )->verify( $request, self::KIND_DRIVE );
	}

	public function verify( $request, $kind ) {
		$key = self::verdictKey( $request, (string) $kind );

		if ( array_key_exists( $key, self::$verdicts ) ) {
			return self::$verdicts[ $key ];
		}

		self::$verdicts[ $key ] = $this->decide( $request, (string) $kind );

		return self::$verdicts[ $key ];
	}

	public static function resetForTests() {
		self::$verdicts = [];
	}

	private function decide( $request, $kind ) {
		$requestId = self::header( $request, self::HEADER_REQUEST_ID );
		$reason    = $this->refusal( $request, $kind );

		if ( null !== $reason ) {
			Log::write(
				'wake_rejected',
				[
					'call'   => $kind,
					'reason' => $reason,
				],
				null,
				null,
				'' === $requestId ? null : $requestId
			);

			return new \WP_Error( self::ERROR_CODE, '', [ 'status' => 401 ] );
		}

		State::rememberNonce( self::header( $request, self::HEADER_NONCE ), self::NONCE_TTL );

		return true;
	}

	private static function verdictKey( $request, $kind ) {
		return hash(
			'sha256',
			implode(
				"\n",
				[
					$kind,
					self::header( $request, self::HEADER_SITE ),
					self::header( $request, self::HEADER_TIMESTAMP ),
					self::header( $request, self::HEADER_NONCE ),
					self::header( $request, self::HEADER_SIGNATURE ),
				]
			)
		);
	}

	private function refusal( $request, $kind ) {
		$site      = self::header( $request, self::HEADER_SITE );
		$timestamp = self::header( $request, self::HEADER_TIMESTAMP );
		$nonce     = self::header( $request, self::HEADER_NONCE );
		$signature = self::header( $request, self::HEADER_SIGNATURE );

		if ( '' === $site || '' === $timestamp || '' === $nonce || '' === $signature ) {
			return 'missing_header';
		}

		$ownSite = (string) call_user_func( $this->siteId );

		if ( '' === $ownSite || ! hash_equals( $ownSite, $site ) ) {
			return 'site_mismatch';
		}

		if ( ! ctype_digit( $timestamp ) || abs( time() - (int) $timestamp ) > self::MAX_SKEW ) {
			return 'stale_timestamp';
		}

		if ( State::nonceSeen( $nonce ) ) {
			return 'replayed_nonce';
		}

		$secret = (string) call_user_func( $this->secret );

		if ( '' === $secret ) {
			return 'no_secret';
		}

		$expected = 'sha256=' . self::sign( $kind, $site, $timestamp, $nonce, $secret );

		if ( ! hash_equals( $expected, $signature ) ) {
			return 'bad_signature';
		}

		return null;
	}

	public static function sign( $kind, $site, $timestamp, $nonce, $secret ) {
		return hash_hmac( 'sha256', $kind . '|' . $site . '|' . $timestamp . '|' . $nonce, $secret );
	}

	public static function header( $request, $name ) {
		if ( ! is_object( $request ) || ! method_exists( $request, 'get_header' ) ) {
			return '';
		}

		$value = $request->get_header( $name );

		return is_string( $value ) ? trim( $value ) : '';
	}
}
