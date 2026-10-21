<?php

declare( strict_types=1 );

use WPML\ATE\Proxies\ProxyRoutingRules;
use WPML\Remote\TrustedDestinations;
use WPML\Remote\UntrustedDestinationException;

final class WPML_Proxy {
	const TIMEOUT         = 30;
	const ROUTE           = '/wpml/v1/proxy';
	const BLOCKED_HEADERS
		= [
			'transfer-encoding',
			'connection',
			'keep-alive',
			'proxy-authenticate',
			'proxy-authorization',
			'te',
			'trailer',
			'upgrade',
			'content-encoding',
			'content-length',
		];


	public static function maybe_handle_request() {
		$rest_route = self::getRestRoute();

		if ( ! self::routeMatches( $rest_route ) ) {
			return;
		}
		if ( \WPML\Setup\Initializer::settingsAreUnrecoverable() ) {
			$error = \WPML\Setup\Initializer::getSettingsRecoveryError();
			self::error( 409, $error['code'], $error['message'] );
			exit;
		}

		$nonce = self::getWPNonce();
		if ( empty( $nonce ) ) {
			self::error(
				401,
				'wp_nonce_missing',
				'Missing REST nonce. Provide the _wpnonce query parameter. If it is missing or always null, check: (1) The client includes _wpnonce in the request URL; (2) Any proxy/CDN/load balancer forwards query strings; (3) Rewrite rules/permalinks route /wp-json/ correctly (or use ?rest_route=/wpml/v1/proxy without pretty permalinks), and ensure your rewrite rules are not blocking or stripping GET parameters; (4) WAF/mod_security is not blocking the _wpnonce parameter.'
			);
			exit;
		}
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			self::error( 401, 'invalid_wp_nonce', 'the wp nonce is invalid' );
			exit;
		}

		if ( ! self::policy()->permits() ) {
			self::error( 403, 'insufficient_permissions', 'You are not allowed to use the WPML proxy.' );
			exit;
		}

		$self = new self();

		$input       = array_merge( (array) $_GET, (array) $_POST );
		$rawBody     = $self->readRawBody();
		$contentType = isset( $_SERVER['CONTENT_TYPE'] ) ? strtolower( (string) $_SERVER['CONTENT_TYPE'] ) : '';
		if ( $rawBody !== '' && strpos( $contentType, 'application/json' ) !== false ) {
			$decoded = json_decode( $rawBody, true );
			if ( is_array( $decoded ) ) {
				$input = array_merge( $input, $decoded );
			}
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET';
		$p      = [
			'url'          => isset( $input['url'] ) ? $input['url'] : null,
			'method'       => strtoupper( isset( $input['method'] ) ? (string) $input['method'] : $method ),
			'query'        => isset( $input['query'] ) ? $input['query'] : null,
			'headers'      => isset( $input['headers'] ) ? $input['headers'] : [],
			'body'         => array_key_exists( 'body', $input ) ? $input['body'] : null,
			'content_type' => isset( $input['content_type'] ) ? $input['content_type'] : null,
			'__raw_body'   => $rawBody,
			'__ct'         => $contentType,
		];

		if ( ! is_array( $p['headers'] ) ) {
			$p['headers'] = $p['headers'] ? [ $p['headers'] ] : [];
		}
		if ( $p['body'] === null && $p['__raw_body'] !== '' ) {
			$p['body'] = $p['__raw_body'];
		}
		if ( empty( $p['content_type'] ) && ! empty( $p['__ct'] ) ) {
			$p['content_type'] = $p['__ct'];
		}

		try {
			$self->validate( $p );
			$url = self::destinations()->assert( $self->buildUrl( (string) $p['url'], $p['query'] ) );

			if ( ! $self->isStaticAssetRelay( $url, (string) $p['method'] ) ) {
				try {
					$lock = \WPML\Container\make( \WPML\TM\ATE\ClonedSites\ApiCommunication::class )->checkCloneSiteLock( $url );
				} catch ( Throwable $t ) {
					$lock = \WPML\TM\ATE\ClonedSites\ReconnectState::get()
						? \WPML\TM\ATE\ClonedSites\ApiCommunication::reconnectingError()
						: null;
				}
				if ( is_wp_error( $lock ) ) {
					self::error( 503, (string) $lock->get_error_code(), $lock->get_error_message() );
					exit;
				}
			}

			$headers = $self->parseHeaders( $p['headers'], isset( $p['content_type'] ) ? (string) $p['content_type'] : null );

			if ( ! isset( $headers['Accept'] ) && ! isset( $headers['accept'] ) ) {
				$headers['Accept'] = '*/*';
			}
			$args = TrustedDestinations::requestArgs(
				[
					'method'  => (string) $p['method'],
					'headers' => $headers,
					'timeout' => self::TIMEOUT,
				]
			);
			if ( strtoupper( (string) $p['method'] ) !== 'GET' && $p['body'] !== null ) {
				$args['body'] = is_array( $p['body'] ) ? \wpml_http_build_query( $p['body'] ) : (string) $p['body'];
			}

			$result = wp_remote_request( $url, $args );
			if ( is_wp_error( $result ) ) {
				throw new RuntimeException( $result->get_error_message() );
			}

			$status      = (int) wp_remote_retrieve_response_code( $result );
			$respHeaders = wp_remote_retrieve_headers( $result );
			if ( is_object( $respHeaders ) && method_exists( $respHeaders, 'getAll' ) ) {
				$respHeaders = $respHeaders->getAll();
			} elseif ( is_object( $respHeaders ) ) {
				$respHeaders = (array) $respHeaders;
			}
			$body        = wp_remote_retrieve_body( $result );
			$respHeaders = $self->filterHeaders( (array) $respHeaders );

			$respHeaders = $self->maybeForceContentTypeByUrl( $respHeaders, $url );

			if ( function_exists( 'status_header' ) ) {
				status_header( (int) $status );
			}
			if ( function_exists( 'http_response_code' ) ) {
				@http_response_code( (int) $status );
			}

			$self->sendCleanResponse( $respHeaders, (string) $body );
		} catch ( UntrustedDestinationException $e ) {
			self::error( 400, 'invalid_url', 'The URL is not a trusted ATE/AMS destination (' . $e->reason() . ').' );
			exit;
		} catch ( Throwable $e ) {
			self::error( 500, 'internal_error', \WPML\WordPress\ClientSafeError::message( 'ATE proxy', $e ) );
			exit;
		}
	}

	private function sendCleanResponse( array $respHeaders, string $body ) {
		if ( function_exists( 'ini_set' ) ) {
			@ini_set( 'display_errors', '0' );
		}
		set_error_handler(
            function () {
                return true;
            },
            E_ALL
        );

		while ( ob_get_level() > 0 ) {
			@ob_end_clean();
		}

		if ( function_exists( 'remove_all_actions' ) ) {
			remove_all_actions( 'shutdown' );
		}

		$respHeaders['Content-Length'] = (string) strlen( (string) $body );

		foreach ( $respHeaders as $name => $value ) {
			if ( $name === '' ) {
				continue;
			}
			$line = $name . ': ' . ( is_array( $value ) ? implode( ', ', $value ) : (string) $value );
			@header( $line, true );
		}

		echo (string) $body;

		if ( function_exists( 'fastcgi_finish_request' ) ) {
			@fastcgi_finish_request();
		}

		exit;
	}

	public static function policy() {
		return \WPML\Request\Policy\Registry::declare(
			\WPML\Request\Policy\Registry::PSEUDO_ROUTE,
			self::ROUTE,
			\WPML\Request\Policy\Policy::capability(
				[ 'translate', 'manage_translations' ],
				\WPML\Request\Policy\Authenticity::restNonce()
			)
		);
	}

	public static function getRestRoute() {
		$rest_route = isset( $_GET['rest_route'] ) ? (string) $_GET['rest_route'] : null;
		if ( ! $rest_route ) {
			$uri        = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
			$qPos       = strpos( $uri, '?' );
			$path       = $qPos === false ? $uri : substr( $uri, 0, $qPos );
			$rest_route = $path;
		}

		return $rest_route;
	}

	private static function getWPNonce() {
		if ( isset( $_SERVER['HTTP_X_WP_NONCE'] ) && $_SERVER['HTTP_X_WP_NONCE'] !== '' ) {
			return (string) $_SERVER['HTTP_X_WP_NONCE'];
		}
		if ( isset( $_GET['_wpnonce'] ) && $_GET['_wpnonce'] !== '' ) {
			return (string) $_GET['_wpnonce'];
		}
		if ( isset( $_POST['_wpnonce'] ) && $_POST['_wpnonce'] !== '' ) {
			return (string) $_POST['_wpnonce'];
		}

		return null;
	}

	private function validate( array $p ) {
		$url    = isset( $p['url'] ) ? (string) $p['url'] : '';
		$method = isset( $p['method'] ) ? strtoupper( (string) $p['method'] ) : '';
		if ( $url === '' || $method === '' ) {
			throw new InvalidArgumentException( 'Required parameters missing.' );
		}

		self::destinations()->assert( $url );
	}

	public static function destinations() {
		return TrustedDestinations::forAteAndAms();
	}

	private function buildUrl( string $url, $query ) {
		if ( is_array( $query ) ) {
			$query = \wpml_http_build_query( $query );
		}
		if ( is_string( $query ) && $query !== '' ) {
			$parts = parse_url( $url );
			if ( $parts ) {
				$base  = ( $parts['scheme'] ?? '' ) !== '' ? $parts['scheme'] . '://' : '';
				$base .= $parts['host'] ?? '';
				$base .= isset( $parts['port'] ) ? ':' . $parts['port'] : '';
				$base .= $parts['path'] ?? '';

				return $base . '?' . $query;
			}
		}

		return $url;
	}

	private function parseHeaders( array $lines, $contentType ) {
		$headers = [];
		foreach ( $lines as $line ) {
			if ( ! is_string( $line ) ) {
				continue;
			}
			$pos = strpos( $line, ':' );
			if ( $pos === false ) {
				continue;
			}
			$name  = trim( substr( $line, 0, $pos ) );
			$value = trim( substr( $line, $pos + 1 ) );
			if ( $name !== '' ) {
				$headers[ $name ] = $value;
			}
		}
		if ( $contentType && ! array_key_exists( 'Content-Type', $headers ) ) {
			$headers['Content-Type'] = $contentType;
		}

		return $headers;
	}

	private function filterHeaders( array $headers ) {
		$out = [];
		foreach ( $headers as $name => $value ) {
			$ln = strtolower( (string) $name );
			if ( in_array( $ln, self::BLOCKED_HEADERS, true ) ) {
				continue;
			}
			$out[ $name ] = is_array( $value ) ? implode( ', ', $value ) : $value;
		}

		return $out;
	}

	private function maybeForceContentTypeByUrl( array $headers, string $url ): array {
		$path = (string) parse_url( $url, PHP_URL_PATH );
		$ext  = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( $ext === '' ) {
			return $headers;
		}

		$mimeMap = [
			'js'   => 'application/javascript',
			'mjs'  => 'application/javascript',
			'css'  => 'text/css; charset=utf-8',
			'json' => 'application/json; charset=utf-8',
			'svg'  => 'image/svg+xml',
			'png'  => 'image/png',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
			'ico'  => 'image/x-icon',
			'html' => 'text/html; charset=utf-8',
			'htm'  => 'text/html; charset=utf-8',
			'txt'  => 'text/plain; charset=utf-8',
			'wasm' => 'application/wasm',
			'pdf'  => 'application/pdf',
		];

		if ( isset( $mimeMap[ $ext ] ) ) {
			unset( $headers['content-type'], $headers['Content-Type'] );
			$headers['Content-Type'] = $mimeMap[ $ext ];
		}

		return $headers;
	}

	private function isStaticAssetRelay( $url, $method ) {
		if ( strtoupper( $method ) !== 'GET' ) {
			return false;
		}

		$path = (string) parse_url( $url, PHP_URL_PATH );
		$ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

		$assetExtensions = [ 'js', 'mjs', 'css', 'map', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'woff', 'woff2', 'ttf', 'wasm' ];

		return in_array( $ext, $assetExtensions, true );
	}

	private function readRawBody() {
		$raw = file_get_contents( 'php://input' );

		return $raw === false ? '' : $raw;
	}

	public static function error( $status_code, $error, $message ) {
		if ( function_exists( 'status_header' ) ) {
			status_header( $status_code );
		}
		if ( function_exists( 'http_response_code' ) ) {
			@http_response_code( $status_code );
		}
		while ( ob_get_level() > 0 ) {
			@ob_end_clean(); }

		$payload = json_encode(
            [
				'error'   => $error,
				'message' => $message,
			]
        );
		@header( 'Content-Type: application/json', true );
		@header( 'Content-Length: ' . strlen( (string) $payload ), true );

		echo (string) $payload;
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			@fastcgi_finish_request(); }
		exit;
	}

	public static function allowedHosts() {
		return ProxyRoutingRules::getAllowedDomains();
	}

	public static function routeMatches( $rest_route ) {
		$prefix = '/' . ltrim( ( function_exists( 'rest_get_url_prefix' ) ? rest_get_url_prefix() : 'wp-json' ), '/' );

		return (
			$rest_route === self::ROUTE
			|| strpos( $rest_route, $prefix . self::ROUTE ) !== false
		);
	}
}

if ( function_exists( 'add_action' ) ) {
	add_action( 'plugins_loaded', [ 'WPML_Proxy', 'maybe_handle_request' ], 0 );
}
