<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\CompositionRoot\Config;

use WPML\UserInterface\Web\Core\SharedKernel\Config\Endpoint\Endpoint;
use WPML\UserInterface\Web\Infrastructure\CompositionRoot\Config\ApiInterface;

class Api implements ApiInterface {


  public function registerRoute(
    Endpoint $endpoint,
    $handle,
    $authorisation
  ) {

    if ( $endpoint->isAjax() ) {
      $this->registerAjaxEndpoint( $endpoint, $handle, $authorisation );
      return;
    }

    $this->registerRestEndpoint(
      $endpoint,
      $endpoint->namespaceWithVersion(),
      $endpoint->path(),
      $endpoint->method(),
      $handle,
      $authorisation,
      $endpoint->args()
    );
  }


  private static function firstInvalidParam( $params, $args ) {
    foreach ( $args as $name => $schema ) {
      if ( ! array_key_exists( $name, $params ) ) {
        continue;
      }

      $valid = \rest_validate_value_from_schema( $params[ $name ], $schema, $name );

      if ( \is_wp_error( $valid ) ) {
        return new \WP_Error(
          'rest_invalid_param',
          /* translators: %s: the name of the request parameter that was refused. */
          sprintf( __( 'Invalid parameter(s): %s', 'sitepress' ), $name ),
          [
            'status' => 400,
            'params' => [ $name => $valid->get_error_message() ],
          ]
        );
      }
    }

    return null;
  }


  private static function scalarGuard( array $args ) {
    $scalarTypes = [ 'string', 'integer', 'number', 'boolean' ];

    foreach ( $args as $name => $schema ) {
      if ( isset( $schema['validate_callback'] )
           || ! isset( $schema['type'] )
           || ! in_array( $schema['type'], $scalarTypes, true ) ) {
        continue;
      }

      $args[ $name ]['validate_callback'] =
        function ( $value ) use ( $name ) {
          if ( null === $value || is_scalar( $value ) ) {
            return true;
          }

          return new \WP_Error(
            'rest_invalid_param',
            /* translators: %s: the name of the request parameter that was refused. */
            sprintf( __( 'Invalid parameter(s): %s', 'sitepress' ), $name ),
            [
              'status' => 400,
              'params' => [
                /* translators: %s: the name of the request parameter that was refused. */
                $name => sprintf( __( '%s must be a single value, not a list.', 'sitepress' ), $name ),
              ],
            ]
          );
        };
    }

    return $args;
  }


  private static function isStateChanging( Endpoint $endpoint ) {
    return \WPML\UserInterface\Web\Core\SharedKernel\Config\Endpoint\MethodType::GET !== $endpoint->method();
  }


  private function registerAjaxEndpoint(
    Endpoint $endpoint,
    $handle,
    $authorisation
  ) {
    $routeWithoutSlashes = \str_replace( '/', '_', $endpoint->route() );

    \WPML\Request\Adapter\Ajax::declare(
      'wpml_api_' . $routeWithoutSlashes,
      self::policyFor( $endpoint, $authorisation, self::isStateChanging( $endpoint )
        ? \WPML\Request\Policy\Authenticity::restNonce()
        : \WPML\Request\Policy\Authenticity::none( 'read-only GET twin of a REST endpoint; no state change' ) )
    );

    add_action(
      'wp_ajax_wpml_api_' . $routeWithoutSlashes,
      function() use ( $endpoint, $handle, $authorisation ) {
        if ( self::isStateChanging( $endpoint ) ) {
          $nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) )
            : '';

          if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            $nonceError = new \WP_Error(
              'rest_cookie_invalid_nonce',
              __( 'Cookie check failed', 'sitepress' ),
              [ 'status' => 403 ]
            );

            $errorResponse = rest_convert_error_to_response( $nonceError );

            return wp_send_json_error( $nonceError, $errorResponse->get_status() );
          }
        }

        $authorisationResult = $authorisation();

        if ( $authorisationResult === false || $authorisationResult === null ) {
          $authorisationResult = new \WP_Error(
            'rest_forbidden',
            __( 'Sorry, you are not allowed to do that.' ),
            [ 'status' => rest_authorization_required_code() ]
          );
        }

        if ( is_wp_error( $authorisationResult ) ) {
          $errorResponse = rest_convert_error_to_response( $authorisationResult );
          return wp_send_json_error( $authorisationResult, $errorResponse->get_status() );
        }

        $json = file_get_contents( 'php://input' );

        $params = $json ? json_decode( $json, true ) : [];
        $params = is_array( $params ) ? $params : [];

        $params = array_merge( $params, $_GET );

        $invalidParam = self::firstInvalidParam( $params, $endpoint->args() );

        if ( $invalidParam ) {
          $errorResponse = rest_convert_error_to_response( $invalidParam );

          return wp_send_json_error( $invalidParam, $errorResponse->get_status() );
        }

        $jsonResponse = $handle( $params );

        http_response_code( $jsonResponse->status );
        header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
        echo \json_encode( $jsonResponse->data );

        wp_die();
      }
    );
  }


  private function registerRestEndpoint(
    Endpoint $endpoint,
    string $name,
    string $path,
    string $method,
    callable $handle,
    callable $authorisation,
    array $args = []
  ) {
    register_rest_route(
      $name,
      $path,
      [
        'methods' => $method,
        'callback' =>
        function( \WP_REST_Request $request ) use ( $handle ) {
          return $handle( $request->get_params() );
        },
        'permission_callback' => \WPML\Request\Adapter\Rest::permission(
          self::policyFor( $endpoint, $authorisation, \WPML\Request\Policy\Authenticity::restTransport() ),
          $name . $path
        ),
        'args'                => self::scalarGuard( $args ),
      ]
    );
  }


  private static function policyFor(
    Endpoint $endpoint,
    $authorisation,
    \WPML\Request\Policy\Authenticity $authenticity
  ) {
    if ( '__return_true' === $endpoint->capability() ) {
      return \WPML\Request\Policy\Policy::publicAccess(
        'endpoint ' . $endpoint->route() . ' is configured public (capability __return_true)',
        $authenticity
      );
    }

    return \WPML\Request\Policy\Policy::authorize(
      function () use ( $authorisation ) {
        return true === $authorisation();
      },
      $authenticity,
      'configured capability "' . implode( '" or "', $endpoint->capabilities() ) . '" via Api::validateRequest (administrators always pass)'
    );
  }


  public function getFullUrl( Endpoint $endpoint ): string {
    $route = $endpoint->route();
    if ( $endpoint->isAjax() ) {
      $routeWithoutSlashes = \str_replace( '/', '_', $route );
      return admin_url( 'admin-ajax.php' ) . '?action=wpml_api_' . $routeWithoutSlashes;
    }
    return get_rest_url( null, $route );
  }


  public function nonce( $name = null ): string {
    $name = $name ?? 'wp_rest';

    return \wp_create_nonce( $name );
  }


  public function validateRequest( $capability ): bool {
    foreach ( (array) $capability as $one ) {
      if ( $one === '__return_true' ) {
        return true;
      }

      if ( \current_user_can( $this->capabilityPlusAdmin( $one ) ) ) {
        return true;
      }
    }

    return false;
  }


  public function capabilityPlusAdmin( string $capability ): string {
    if ( current_user_can( WPML_CAP_MANAGE_OPTIONS ) ) {
      return WPML_CAP_MANAGE_OPTIONS;
    }

    return $capability;
  }


  public function responseJsonSuccess( $data ) {
      return \rest_ensure_response( new \WP_REST_Response( $data, 200 ) );
  }


  public function responseJsonError( $data ) {
      return \rest_ensure_response( new \WP_REST_Response( $data, 500 ) );
  }


  public function responseJsonWithStatusCode( $data, $status_code ) {
      return \rest_ensure_response( new \WP_REST_Response( $data, $status_code ) );
  }


  public function isRestRequest(): bool {
    return defined( 'REST_REQUEST' );
  }


}
