<?php

namespace WPML\Infrastructure\WordPress\SharedKernel\WpmlOrgClient\Domain\Api\Endpoints\PluginReport;

use WPML\Core\SharedKernel\Component\WpmlOrgClient\Domain\Api\Endpoints\PluginReportInterface;
use WPML\Core\SharedKernel\Component\WpmlOrgClient\Domain\Api\SupportUrl;

class PluginReport implements PluginReportInterface {

  const ENDPOINT = '/support/plugin-reports';

  const REPORT_PAGE = '/support';

  private $supportUrl;


  public function __construct( SupportUrl $supportUrl ) {
    $this->supportUrl = $supportUrl;
  }


  public function run( string $siteKey, array $debug, bool $consent = true ): array {
    $body = wp_json_encode(
      [
        'site_key' => $siteKey,
        'debug'    => $debug,
        'consent'  => $consent,
      ]
    );

    if ( ! is_string( $body ) ) {
      return $this->errorResult( 'encode_failed' );
    }

    $response = wp_remote_post(
      $this->supportUrl->get() . self::ENDPOINT,
      [
        'timeout' => 15,
        'headers' => [ 'Content-Type' => 'application/json' ],
        'body'    => $body,
      ]
    );

    if ( is_wp_error( $response ) ) {
      return $this->errorResult( 'transport: ' . $response->get_error_code() );
    }

    $responseCode = wp_remote_retrieve_response_code( $response );
    $decodedBody  = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $this->isSubscriptionExpiredResponse( $responseCode, $decodedBody ) ) {
      return $this->errorResult( 'subscription_expired' );
    }

    if (
      ! in_array( $responseCode, [ 200, 201 ], true ) ||
      ! is_array( $decodedBody ) ||
      ! isset( $decodedBody['report_token'] ) ||
      ! is_string( $decodedBody['report_token'] ) ||
      $decodedBody['report_token'] === ''
    ) {
      return $this->errorResult( 'bad_response: http ' . (int) $responseCode );
    }

    return [
      'success'         => true,
      'reportUrl'       => $this->supportUrl->get() . self::REPORT_PAGE
        . '?plugin_report=' . rawurlencode( $decodedBody['report_token'] ),
      'isResponseError' => false,
      'errorCode'       => '',
    ];
  }


  private function isSubscriptionExpiredResponse( $responseCode, $decodedBody ): bool {
    return $responseCode === 422 &&
      is_array( $decodedBody ) &&
      isset( $decodedBody['error'] ) &&
      $decodedBody['error'] === 'subscription_expired';
  }


  private function errorResult( string $errorCode ): array {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
      error_log( 'WPML plugin report send failed: ' . $errorCode );
    }

    return [
      'success'         => false,
      'reportUrl'       => '',
      'isResponseError' => true,
      'errorCode'       => $errorCode,
    ];
  }


}
