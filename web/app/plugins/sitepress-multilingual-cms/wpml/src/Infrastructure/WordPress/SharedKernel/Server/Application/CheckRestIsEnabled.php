<?php

namespace WPML\Infrastructure\WordPress\SharedKernel\Server\Application;

use WPML\Core\SharedKernel\Component\Server\Domain\CacheInterface;
use WPML\Core\SharedKernel\Component\Server\Domain\CheckRestIsEnabledInterface;

class CheckRestIsEnabled implements CheckRestIsEnabledInterface {

  private $cache;

  const CACHE_KEY   = 'wpml_rest_api_status';
  const CACHE_FIELD = 'enabled';

  const CACHE_TTL_PASS = 60 * 60 * 12;
  const CACHE_TTL_FAIL = 60 * 10;


  public function __construct( CacheInterface $cache ) {
    $this->cache = $cache;
  }


  public function isEnabled( bool $useCache = false ): bool {
    if ( $useCache ) {
      $cached = $this->readCache();
      if ( $cached !== null ) {
        return $cached;
      }
    }

    $result = $this->checkRestApiAvailability();
    $this->cache->set(
      self::CACHE_KEY,
      [ self::CACHE_FIELD => $result ],
      $result ? self::CACHE_TTL_PASS : self::CACHE_TTL_FAIL
    );

    return $result;
  }


  private function readCache() {
    $cached = $this->cache->get( self::CACHE_KEY );

    if ( is_array( $cached ) && array_key_exists( self::CACHE_FIELD, $cached ) ) {
      return (bool) $cached[ self::CACHE_FIELD ];
    }

    return null;
  }


  private function testEndpoint( string $endpoint ): bool {
    $args = [
      'timeout'     => 20,
      'redirection' => 5,
      'sslverify'   => false,
      'headers'     => [
        'Accept' => 'application/json',
      ],
      'cookies'     => $this->getCookiesWithoutSessionId(),
    ];

    if ( isset( $_SERVER['PHP_AUTH_USER'] )
         && isset( $_SERVER['PHP_AUTH_PW'] )
    ) {
      $args['headers']['Authorization'] = 'Basic '
                                          . base64_encode(
                                            $_SERVER['PHP_AUTH_USER']
                                            . ':'
                                            . $_SERVER['PHP_AUTH_PW']
                                          );
    }

    $endpoint = add_query_arg( 'cachebuster', time(), $endpoint );
    $response = wp_remote_get( $endpoint, $args );

    if ( is_wp_error( $response ) ) {
      return false;
    }

    $status_code = wp_remote_retrieve_response_code( $response );
    if ( $status_code >= 400 ) {
      if ( $status_code === 403 ) {
        return $this->testEndpointInternally( $this->getRestRoute(), $this->getRestQueryParams() );
      }
      return false;
    }

    return $this->validateResponse( wp_remote_retrieve_body( $response ) );
  }


  private function validateResponse( string $body ): bool {
    if ( empty( $body ) ) {
      return false;
    }
    $response = json_decode( $body, true );

    if ( empty( $response ) || ! is_array( $response ) ) {
      return false;
    }

    if ( ! isset( $response['success'] ) || ! $response['success'] ) {
      return false;
    }

    $payload = $response['data'] ?? null;

    return is_array( $payload )
           && isset( $payload['status'], $payload['get_parameters'] )
           && $payload['status'] === 'valid';
  }


  private function testEndpointInternally( string $route, array $query_params ) {
    $request = new \WP_REST_Request( 'GET', $route );
    $request->set_query_params( $query_params );

    $server = rest_get_server();
    $response = $server->dispatch( $request );

    if ( is_wp_error( $response ) ) {
      return false;
    }

    $status_code = $response->get_status();
    if ( $status_code >= 400 ) {
      return false;
    }

    $data = $response->get_data();

    if ( empty( $data ) || ! is_array( $data ) ) {
      return false;
    }

    if ( ! isset( $data['success'] ) || ! $data['success'] ) {
      return false;
    }

    $payload = $data['data'] ?? false;

    return is_array( $payload )
           && isset( $payload['status'], $payload['get_parameters'] )
           && $payload['status'] === 'valid';
  }


  private function getCookiesWithoutSessionId() {
    return array_diff_key( $_COOKIE, [ 'PHPSESSID' => '' ] );
  }


  private function getRestRoute(): string {
    return '/wpml/v1/rest/status';
  }


  private function getRestQueryParams(): array {
    return [
      'test_get_parameter' => true,
      'cachebuster'        => time(),
    ];
  }


  public function getEndpoint(): string {
    $endpoint = get_rest_url( null, ltrim( $this->getRestRoute(), '/' ) );

    foreach ( $this->getRestQueryParams() as $key => $value ) {
      $endpoint = add_query_arg( $key, $value, $endpoint );
    }

    return $endpoint;
  }


  private function checkRestApiAvailability(): bool {
    if ( ! class_exists( 'WP_REST_Server' ) ) {
      return false;
    }

    $rest_enabled = apply_filters( 'rest_enabled', true );
    if ( ! $rest_enabled ) {
      return false;
    }

    $server = rest_get_server();
    $routes = $server->get_routes();

    if ( empty( $routes ) ) {
      return false;
    }

    $result = $this->testEndpoint( $this->getEndpoint() );
    if ( ! $result ) {
      $result = $this->testEndpoint( $this->getEndpoint() );
    }

    return $result;
  }


}
