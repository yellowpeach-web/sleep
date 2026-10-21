<?php

namespace WPML\Infrastructure\WordPress\SharedKernel\WpmlOrgClient\Domain\Api\Endpoints\PostHogRecording;

use WPML\Core\Component\PostHog\Domain\TrackingMode;
use WPML\Core\SharedKernel\Component\WpmlOrgClient\Domain\Api\ApiUrl;
use WPML\Core\SharedKernel\Component\WpmlOrgClient\Domain\Api\Endpoints\PostHogRecordingInterface;

class PostHogRecording implements PostHogRecordingInterface {

  const ENDPOINT = '/?action=should_record_site';

  private $apiUrl;


  public function __construct( ApiUrl $apiUrl ) {
    $this->apiUrl = $apiUrl;
  }


  public function run(
    string $siteKey,
    string $recordingMode = 'default',
    string $wpmlVersion = '',
    string $teaState = '',
    array $context = []
  ): array {
    $body = [
      'site_key'       => $siteKey,
      'recording_mode' => $recordingMode,
    ];

    if ( $wpmlVersion !== '' ) {
      $body['wpml_version'] = $wpmlVersion;
    }

    if ( $teaState !== '' ) {
      $body['tea_state'] = $teaState;
    }

    $body = $this->appendContext( $body, $context );

    $response = wp_remote_post(
      $this->apiUrl->get() . self::ENDPOINT,
      [ 'body' => $body ]
    );

    if ( is_wp_error( $response ) ) {
      return $this->errorResult();
    }

    $responseCode = wp_remote_retrieve_response_code( $response );
    $body         = wp_remote_retrieve_body( $response );
    $decodedBody  = json_decode( $body, true );

    if ( $responseCode !== 200 || ! is_array( $decodedBody ) ) {
      return $this->errorResult();
    }

    $trackingMode = $this->parseTrackingMode( $decodedBody );

    return [
      'success'               => true,
      'shouldRecord'          => TrackingMode::toBool( $trackingMode ),
      'trackingMode'          => $trackingMode,
      'isResponseError'       => false,
      'recordOptOutDashboard' => $this->parseOptOutDashboard( $decodedBody ),
      'optOutExpiry'          => $this->parseOptOutExpiry( $decodedBody ),
    ];
  }


  private function errorResult(): array {
    return [
      'success'               => false,
      'shouldRecord'          => false,
      'trackingMode'          => TrackingMode::DISABLED,
      'isResponseError'       => true,
      'recordOptOutDashboard' => null,
      'optOutExpiry'          => null,
    ];
  }


  private function parseOptOutDashboard( array $decodedBody ): ?bool {
    if ( ! array_key_exists( 'record_optout_dashboard', $decodedBody ) ) {
      return null;
    }

    return (bool) $decodedBody['record_optout_dashboard'];
  }


  private function parseOptOutExpiry( array $decodedBody ): ?int {
    if ( ! isset( $decodedBody['optout_expiry'] ) || ! is_numeric( $decodedBody['optout_expiry'] ) ) {
      return null;
    }

    return (int) $decodedBody['optout_expiry'];
  }


  private function parseTrackingMode( array $decodedBody ): string {
    if ( isset( $decodedBody['tracking_mode'] ) && is_string( $decodedBody['tracking_mode'] ) ) {
      $mode = $decodedBody['tracking_mode'];

      $mappedMode = TrackingMode::fromApiMode( $mode );
      if ( $mappedMode !== null ) {
        return $mappedMode;
      }

      if ( TrackingMode::isValid( $mode ) ) {
        return $mode;
      }
    }

    if ( isset( $decodedBody['should_record'] ) ) {
      return TrackingMode::fromBool( (bool) $decodedBody['should_record'] );
    }

    return TrackingMode::DISABLED;
  }


  private function appendContext( array $body, array $context ): array {
    if ( array_key_exists( 'during_setup', $context ) ) {
      $body['during_setup'] = $context['during_setup'];
    }

    if ( isset( $context['setup_tea_choice'] ) && $context['setup_tea_choice'] !== '' ) {
      $body['setup_tea_choice'] = $context['setup_tea_choice'];
    }

    if ( array_key_exists( 'record_optout_dashboard_done', $context ) ) {
      $body['record_optout_dashboard_done'] = $context['record_optout_dashboard_done'];
    }

    return $body;
  }


}
