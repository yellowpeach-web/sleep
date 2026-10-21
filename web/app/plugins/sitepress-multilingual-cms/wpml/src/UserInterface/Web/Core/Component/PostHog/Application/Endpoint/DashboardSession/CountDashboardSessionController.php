<?php

namespace WPML\UserInterface\Web\Core\Component\PostHog\Application\Endpoint\DashboardSession;

use WPML\Core\Component\PostHog\Application\Service\CountDashboardSessionService;
use WPML\Core\Port\Endpoint\EndpointInterface;

class CountDashboardSessionController implements EndpointInterface {

  const MAX_SESSION_ID_LENGTH = 100;

  private $countService;


  public function __construct( CountDashboardSessionService $countService ) {
    $this->countService = $countService;
  }


  public function handle( $requestData = null ): array {
    $sessionId = $this->sanitizeSessionId( $requestData );

    if ( $sessionId === '' ) {
      return [ 'success' => false, 'error' => 'invalid_session_id' ];
    }

    $counted = $this->countService->count( $sessionId );

    return [
      'success' => true,
      'data'    => [
        'counted'   => $counted,
        'count'     => $this->countService->getCount(),
        'completed' => $this->countService->isCompleted(),
      ],
    ];
  }


  private function sanitizeSessionId( $requestData ): string {
    if (
      ! is_array( $requestData ) ||
      ! isset( $requestData['session_id'] ) ||
      ! is_string( $requestData['session_id'] )
    ) {
      return '';
    }

    $sessionId = (string) preg_replace( '/[^A-Za-z0-9._-]/', '', $requestData['session_id'] );

    return substr( $sessionId, 0, self::MAX_SESSION_ID_LENGTH );
  }


}
