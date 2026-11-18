<?php

namespace WPML\TM\ATE\Handshake;

class Acceptance {

	const HEADER = 'X-ATE-Handshake';
	const VALUE  = 'level1';

	public static function addHooks() {
		add_filter( 'http_response', [ self::class, 'onHttpResponse' ], 10, 3 );
	}

	public static function accepted() {
		return (int) State::getKey( 'handshake_accepted_at' ) > 0 && ! self::refused();
	}

	public static function refused() {
		return (int) State::getKey( 'handshake_refused_at' ) > 0;
	}

	public static function onHttpResponse( $response, $args = [], $url = '' ) {
		try {
			if ( ! is_array( $response ) || ! self::isDeclaredAteCall( (string) $url ) ) {
				return $response;
			}

			$status = isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;

			self::read( self::carriesAcceptance( $response ), $status, (string) $url );
		} catch ( \Throwable $e ) {
			return $response;
		}

		return $response;
	}

	public static function read( $accepts, $status, $url ) {
		$acceptedAt = (int) State::getKey( 'handshake_accepted_at' );
		$refusedAt  = (int) State::getKey( 'handshake_refused_at' );

		if ( $accepts ) {
			if ( $acceptedAt < 1 || $refusedAt > 0 ) {
				State::update(
					[
						'handshake_accepted_at'    => Clock::now(),
						'handshake_refused_at'     => 0,
						'handshake_refused_route'  => '',
						'handshake_refused_status' => 0,
					]
				);
				Log::write( 'acceptance_recorded', [ 'route' => self::route( $url ), 'status' => (int) $status ] );
			}

			return;
		}

		if ( $refusedAt > 0 || $status < 100 || $status >= 500 ) {
			return;
		}

		State::update(
			[
				'handshake_refused_at'     => Clock::now(),
				'handshake_refused_route'  => self::route( $url ),
				'handshake_refused_status' => (int) $status,
			]
		);
		Log::write(
			'acceptance_refused',
			[
				'route'       => self::route( $url ),
				'status'      => (int) $status,
				'accepted_at' => $acceptedAt,
			]
		);
	}

	public static function isDeclaredAteCall( $url ) {
		if ( '' === $url ) {
			return false;
		}

		$base = rtrim( (string) ( new \WPML_TM_ATE_AMS_Endpoints() )->get_ATE_base_url(), '/' );

		if ( '' === $base || 0 !== strpos( $url, $base . '/api/' ) ) {
			return false;
		}

		if ( \WPML_TM_ATE_AMS_Endpoints::SERVICE_ATE !== \WPML_TM_ATE_AMS_Endpoints::serviceOfUrl( $url ) ) {
			return false;
		}

		$boxReads = [
			self::route( $base . AteClient::ENDPOINT_STATE ),
			self::route( $base . AteClient::ENDPOINT_PROBE ),
			self::route( $base . Corrections\Client::ENDPOINT_OFFERED ),
			self::route( $base . Corrections\Client::ENDPOINT_DECIDE ),
		];

		if ( in_array( self::route( $url ), $boxReads, true ) ) {
			return false;
		}

		if ( self::route( $url ) === self::route( $base . \WPML_TM_ATE_AMS_Endpoints::ENDPOINTS_LANGUAGES ) ) {
			return false;
		}

		$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
		$args  = [];
		parse_str( $query, $args );

		return isset( $args[ Declaration::PARAM ] ) && Declaration::VALUE === $args[ Declaration::PARAM ];
	}

	private static function carriesAcceptance( array $response ) {
		$headers = isset( $response['headers'] ) ? $response['headers'] : [];
		$values  = [];

		if ( $headers instanceof \ArrayAccess ) {
			$value  = $headers[ strtolower( self::HEADER ) ];
			$values = null === $value ? [] : (array) $value;
		} elseif ( is_array( $headers ) ) {
			foreach ( $headers as $name => $value ) {
				if ( 0 === strcasecmp( (string) $name, self::HEADER ) ) {
					$values = array_merge( $values, (array) $value );
				}
			}
		}

		foreach ( $values as $value ) {
			foreach ( explode( ',', (string) $value ) as $one ) {
				if ( 0 === strcasecmp( trim( $one ), self::VALUE ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private static function route( $url ) {
		return (string) wp_parse_url( $url, PHP_URL_PATH );
	}
}
