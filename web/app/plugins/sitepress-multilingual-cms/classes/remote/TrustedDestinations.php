<?php

namespace WPML\Remote;

final class TrustedDestinations {

	const ERROR_CODE = 'wpml_untrusted_destination';

	const REASON_MALFORMED   = 'malformed_url';
	const REASON_USER_INFO   = 'user_info';
	const REASON_SCHEME      = 'scheme';
	const REASON_HOST        = 'host';
	const REASON_PORT        = 'port';
	const REASON_PATH        = 'path';

	private $destinations = [];

	private function __construct( array $destinations ) {
		$this->destinations = $destinations;
	}

	public static function forAteAndAms( ?\WPML_TM_ATE_AMS_Endpoints $endpoints = null ) {
		$endpoints = $endpoints ?: new \WPML_TM_ATE_AMS_Endpoints();

		return self::fromBaseUrls(
			[
				$endpoints->get_base_url( \WPML_TM_ATE_AMS_Endpoints::SERVICE_ATE ),
				$endpoints->get_base_url( \WPML_TM_ATE_AMS_Endpoints::SERVICE_AMS ),
			]
		);
	}

	public static function fromBaseUrls( array $baseUrls, array $allowedPorts = [] ) {
		$destinations = [];

		foreach ( $baseUrls as $baseUrl ) {
			$parts = self::parse( $baseUrl );
			if ( ! $parts ) {
				throw new \InvalidArgumentException( 'A trusted destination must be a plain absolute URL.' );
			}
			if ( self::hasUserInfo( $baseUrl, $parts ) ) {
				throw new \InvalidArgumentException( 'A trusted destination must not carry user-info.' );
			}

			$scheme = strtolower( (string) $parts['scheme'] );
			$host   = strtolower( (string) $parts['host'] );
			if ( ! self::isHostName( $host ) || ! in_array( $scheme, [ 'https', 'http' ], true ) ) {
				throw new \InvalidArgumentException( 'A trusted destination must be an http(s) URL with a plain host name.' );
			}

			$ports = [];
			if ( isset( $allowedPorts[ $host ] ) && is_array( $allowedPorts[ $host ] ) ) {
				$ports = $allowedPorts[ $host ];
			} elseif ( $allowedPorts && array_values( $allowedPorts ) === $allowedPorts ) {
				$ports = $allowedPorts;
			}

			$destinations[] = [
				'scheme' => $scheme,
				'host'   => $host,
				'port'   => isset( $parts['port'] ) ? (int) $parts['port'] : self::defaultPort( $scheme ),
				'path'   => self::normalizePath( isset( $parts['path'] ) ? (string) $parts['path'] : '' ),
				'ports'  => array_values( array_map( 'intval', $ports ) ),
			];
		}

		if ( [] === $destinations ) {
			throw new \InvalidArgumentException( 'At least one trusted destination is required.' );
		}

		return new self( $destinations );
	}

	public function hosts() {
		return array_values( array_unique( array_column( $this->destinations, 'host' ) ) );
	}

	public function isTrusted( $url ) {
		return true === $this->check( $url );
	}

	public function assert( $url ) {
		$verdict = $this->check( $url );
		if ( true === $verdict ) {
			return (string) $url;
		}

		throw new UntrustedDestinationException( $verdict['reason'], $verdict['message'] );
	}

	public function check( $url ) {
		$parts = self::parse( $url );
		if ( ! $parts ) {
			return self::deny( self::REASON_MALFORMED, 'The URL is not a plain absolute URL.' );
		}
		if ( self::hasUserInfo( $url, $parts ) ) {
			return self::deny( self::REASON_USER_INFO, 'The URL carries user-info.' );
		}

		$scheme = strtolower( (string) $parts['scheme'] );
		$host   = strtolower( (string) $parts['host'] );
		if ( ! self::isHostName( $host ) ) {
			return self::deny( self::REASON_HOST, 'The URL host is not a plain host name.' );
		}

		$path = isset( $parts['path'] ) ? (string) $parts['path'] : '';

		$hostMatched = null;
		foreach ( $this->destinations as $destination ) {
			if ( $host !== $destination['host'] ) {
				continue;
			}
			$hostMatched = $destination;

			if ( $scheme !== $destination['scheme'] ) {
				continue;
			}

			$port = isset( $parts['port'] ) ? (int) $parts['port'] : self::defaultPort( $scheme );
			if ( $port !== $destination['port'] && ! in_array( $port, $destination['ports'], true ) ) {
				continue;
			}

			$pathVerdict = self::checkPath( $path, $destination['path'] );
			if ( true !== $pathVerdict ) {
				return $pathVerdict;
			}

			return true;
		}

		if ( null === $hostMatched ) {
			return self::deny( self::REASON_HOST, 'The URL host is not a trusted destination.' );
		}
		if ( $scheme !== $hostMatched['scheme'] ) {
			return self::deny( self::REASON_SCHEME, 'The URL scheme is not the destination\'s scheme.' );
		}

		return self::deny( self::REASON_PORT, 'The URL port is not the destination\'s port.' );
	}

	public static function requestArgs( array $args = [] ) {
		$args['redirection'] = 0;

		return $args;
	}

	public static function checkPublicHttps( $url ) {
		$parts = self::parse( $url );
		if ( ! $parts ) {
			return self::deny( self::REASON_MALFORMED, 'The URL is not a plain absolute URL.' );
		}
		if ( self::hasUserInfo( $url, $parts ) ) {
			return self::deny( self::REASON_USER_INFO, 'The URL carries user-info.' );
		}
		if ( 'https' !== strtolower( (string) $parts['scheme'] ) ) {
			return self::deny( self::REASON_SCHEME, 'The URL is not https.' );
		}
		if ( ! self::isHostName( strtolower( (string) $parts['host'] ) ) ) {
			return self::deny( self::REASON_HOST, 'The URL host is not a plain host name.' );
		}

		return true;
	}

	private static function parse( $url ) {
		if ( ! is_string( $url ) || '' === $url || strlen( $url ) > 8192 ) {
			return false;
		}
		if ( preg_match( '/[\x00-\x20\x7f\\\\]/', $url ) ) {
			return false;
		}
		if ( ! preg_match( '#^[a-z][a-z0-9+.-]*://#i', $url ) ) {
			return false;
		}
		if ( '' === self::authority( $url ) ) {
			return false;
		}

		$parts = parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return false;
		}

		return $parts;
	}

	private static function authority( $url ) {
		$authority = substr( $url, strpos( $url, '://' ) + 3 );

		return (string) preg_split( '#[/?\#]#', $authority, 2 )[0];
	}

	private static function hasUserInfo( $url, array $parts ) {
		return isset( $parts['user'] ) || isset( $parts['pass'] ) || false !== strpos( self::authority( $url ), '@' );
	}

	private static function isHostName( $host ) {
		return '' !== $host
			&& strlen( $host ) <= 253
			&& (bool) preg_match( '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*$/', $host );
	}

	private static function checkPath( $path, $destinationPath ) {
		if ( '' !== $path && '/' !== $path[0] ) {
			return self::deny( self::REASON_PATH, 'The URL path is not absolute.' );
		}
		foreach ( explode( '/', $path ) as $segment ) {
			if ( '.' === $segment || '..' === $segment || preg_match( '/^%2e/i', $segment ) ) {
				return self::deny( self::REASON_PATH, 'The URL path contains dot-segments.' );
			}
		}
		if ( '' !== $destinationPath ) {
			$prefix = $destinationPath . '/';
			if ( $path !== $destinationPath && 0 !== strpos( $path, $prefix ) ) {
				return self::deny( self::REASON_PATH, 'The URL path is outside the destination path.' );
			}
		}

		return true;
	}

	private static function defaultPort( $scheme ) {
		return 'http' === $scheme ? 80 : 443;
	}

	private static function normalizePath( $path ) {
		$path = rtrim( $path, '/' );

		return '' === $path || '/' === $path ? '' : $path;
	}

	private static function deny( $reason, $message ) {
		return [ 'reason' => $reason, 'message' => $message ];
	}
}
