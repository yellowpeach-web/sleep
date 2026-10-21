<?php

namespace WPML\UserInterface\Web\Core\Component\ATE\Application\Endpoint;

use WPML\Core\Component\Communication\Application\Service\DismissNoticeService;
use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\Core\SharedKernel\Component\User\Application\Query\UserQueryInterface;

class DismissPendingSuggestionsNudgeController implements EndpointInterface {

  const NOTICE_ID_BASE = 'wpml-improve-translations-pending';

  const DISMISS_DAYS = 7;

  const SECONDS_PER_DAY = 86400;

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

    $this->dismissNoticeService->dismissPerUser( self::noticeIdForDate( gmdate( 'Y-m-d' ) ), $user->getId() );

    return [ 'success' => true ];
  }


  public static function noticeIdForDate( string $date ): string {
    return self::NOTICE_ID_BASE . ':' . $date;
  }


  public static function dismissedNoticeIdsToCheck(): array {
    $ids = [];
    for ( $day = 0; $day < self::DISMISS_DAYS; $day++ ) {
      $ids[] = self::noticeIdForDate( gmdate( 'Y-m-d', time() - ( $day * self::SECONDS_PER_DAY ) ) );
    }

    return $ids;
  }


}
