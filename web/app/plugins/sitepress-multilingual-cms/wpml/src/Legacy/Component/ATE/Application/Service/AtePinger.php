<?php

namespace WPML\Legacy\Component\ATE\Application\Service;

use WPML\Core\SharedKernel\Component\ATE\Application\Repository\AteReachabilityRepositoryInterface;
use WPML\Core\SharedKernel\Component\ATE\Application\Service\AtePingerInterface;
use WPML\Core\SharedKernel\Component\ATE\Application\Service\TeaLoggerInterface;
use WPML\Posts\UntranslatedCount;

class AtePinger implements AtePingerInterface {

  private $apiClient;

  private $reachabilityRepository;

  private $untranslatedCount;

  private $logger;


  public function __construct(
    \WPML_TM_ATE_API $apiClient,
    AteReachabilityRepositoryInterface $reachabilityRepository,
    UntranslatedCount $untranslatedCount,
    TeaLoggerInterface $logger
  ) {
    $this->apiClient              = $apiClient;
    $this->reachabilityRepository = $reachabilityRepository;
    $this->untranslatedCount      = $untranslatedCount;
    $this->logger                 = $logger;
  }


  public function notifyTeaEnabled( string $trigger ): bool {
    list( $response, $startMs ) = $this->sendTeaStatus( true, $trigger );

    $reachable = $this->extractReachable( $response, $trigger, $startMs );

    return $this->persist( $trigger, $reachable );
  }


  public function notifyTeaDisabled( string $trigger ): void {
    $this->sendTeaStatus( false, $trigger );
  }


  public function checkReachability( string $trigger ) {
    $this->logger->reachabilityProbeRequest( $trigger );

    $startMs  = (int) ( microtime( true ) * 1000 );
    $response = $this->apiClient->check_delivery_reachability(
      [ 'site_identifier' => (string) \wpml_get_site_id( \WPML_TM_ATE::SITE_ID_SCOPE ) ],
      [ 'wpml_ate_bypass_breaker' => true ]
    );
    $elapsedMs = (int) ( microtime( true ) * 1000 ) - $startMs;

    if ( \is_wp_error( $response ) ) {
      if ( self::isEndpointMissing( $response ) ) {
        $this->logger->reachabilityProbeUnsupported( $trigger );

        return null;
      }

      $this->logger->reachabilityProbeHttpError( $trigger, $response->get_error_message(), $elapsedMs );

      return $this->persist( $trigger, false );
    }

    if ( ! is_object( $response ) || ! isset( $response->reachable ) ) {
      $snippet = is_scalar( $response ) ? (string) $response : (string) json_encode( $response );
      $reason  = is_object( $response ) ? 'reachable_key_missing' : 'non_object_response';
      $this->logger->reachabilityProbeMalformedBody( $trigger, $snippet, $elapsedMs, $reason );

      return $this->persist( $trigger, false );
    }

    $reachable = (bool) $response->reachable;
    $this->logger->reachabilityProbeResponse(
      $trigger,
      isset( $response->accepted ) ? (bool) $response->accepted : null,
      $reachable,
      isset( $response->checked_url ) ? (string) $response->checked_url : null,
      $elapsedMs
    );

    return $this->persist( $trigger, $reachable );
  }


  private static function isEndpointMissing( $error ): bool {
    return (int) $error->get_error_code() === 404;
  }


  private function persist( string $trigger, bool $reachable ): bool {
    $this->reachabilityRepository->save( $reachable );
    $this->logger->reachabilityPersisted( $trigger, $reachable );

    return $reachable;
  }


  private function sendTeaStatus( bool $active, string $trigger ): array {
    $estimatedJobs = $this->countEstimatedJobs();

    $this->logger->teaStatusRequest( $trigger, $active, $estimatedJobs );

    $startMs  = (int) ( microtime( true ) * 1000 );
    $response = $this->apiClient->notify_tea_status(
      [
        'site_identifier' => (string) \wpml_get_site_id( \WPML_TM_ATE::SITE_ID_SCOPE ),
        'active'          => $active,
        'estimated_jobs'  => $estimatedJobs,
      ]
    );

    return [ $response, $startMs ];
  }


  private function countEstimatedJobs(): int {
    try {
      return $this->untranslatedCount->countTotal( \wpml_collect(), $GLOBALS['wpdb'] );
    } catch ( \Throwable $e ) {
      return 0;
    }
  }


  private function extractReachable( $response, string $trigger, int $startMs ): bool {
    $elapsedMs = (int) ( microtime( true ) * 1000 ) - $startMs;

    if ( \is_wp_error( $response ) ) {
      $this->logger->teaStatusHttpError( $trigger, $response->get_error_message(), $elapsedMs );
      return false;
    }

    if ( ! is_object( $response ) ) {
      $snippet = is_scalar( $response ) ? (string) $response : (string) json_encode( $response );
      $this->logger->teaStatusMalformedBody( $trigger, $snippet, $elapsedMs, 'non_object_response' );
      return false;
    }

    if ( ! isset( $response->reachable ) ) {
      $this->logger->teaStatusMalformedBody(
        $trigger,
        (string) json_encode( $response ),
        $elapsedMs,
        'reachable_key_missing'
      );
      return false;
    }

    $reachable = (bool) $response->reachable;
    $this->logger->teaStatusResponse(
      $trigger,
      isset( $response->accepted ) ? (bool) $response->accepted : null,
      $reachable,
      $elapsedMs
    );
    return $reachable;
  }


}
