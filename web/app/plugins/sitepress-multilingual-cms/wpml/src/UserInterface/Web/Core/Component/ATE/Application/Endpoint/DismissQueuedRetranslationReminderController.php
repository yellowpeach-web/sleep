<?php

namespace WPML\UserInterface\Web\Core\Component\ATE\Application\Endpoint;

use WPML\Core\Component\Communication\Application\Service\DismissNoticeService;
use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\Core\SharedKernel\Component\User\Application\Query\UserQueryInterface;

class DismissQueuedRetranslationReminderController implements EndpointInterface {

  const NOTICE_ID_BASE = 'wpml-queued-retranslation-reminder';

  private $dismissNoticeService;

  private $userQuery;


  public function __construct(
    DismissNoticeService $dismissNoticeService,
    UserQueryInterface $userQuery
  ) {
    $this->dismissNoticeService = $dismissNoticeService;
    $this->userQuery = $userQuery;
  }


  public function handle( $requestData = null ): array {
    $user = $this->userQuery->getCurrent();
    if ( ! $user ) {
      return [ 'success' => false ];
    }

    $queuedSince = isset( $requestData['queuedSince'] ) && is_string( $requestData['queuedSince'] )
      ? $requestData['queuedSince']
      : '';
    $queuedSince = htmlspecialchars( strip_tags( $queuedSince ) );

    if ( $queuedSince === '' ) {
      return [ 'success' => true ];
    }

    $this->dismissNoticeService->dismissPerUser( self::noticeId( $queuedSince ), $user->getId() );

    return [ 'success' => true ];
  }


  public static function noticeId( string $queuedSince ): string {
    return self::NOTICE_ID_BASE . ':' . $queuedSince;
  }


}
