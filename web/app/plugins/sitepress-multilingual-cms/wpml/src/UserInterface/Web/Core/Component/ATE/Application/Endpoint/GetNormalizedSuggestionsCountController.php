<?php

namespace WPML\UserInterface\Web\Core\Component\ATE\Application\Endpoint;

use WPML\Core\Component\ATE\Application\Query\NormalizedSuggestionsInterface;
use WPML\Core\Component\Communication\Application\Query\DismissedNoticesQuery;
use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\Core\SharedKernel\Component\User\Application\Query\UserQueryInterface;

class GetNormalizedSuggestionsCountController implements EndpointInterface {

  private $normalizedSuggestionsQuery;

  private $dismissedNoticesQuery;

  private $userQuery;


  public function __construct(
    NormalizedSuggestionsInterface $normalizedSuggestionsQuery,
    DismissedNoticesQuery $dismissedNoticesQuery,
    UserQueryInterface $userQuery
  ) {
    $this->normalizedSuggestionsQuery = $normalizedSuggestionsQuery;
    $this->dismissedNoticesQuery = $dismissedNoticesQuery;
    $this->userQuery = $userQuery;
  }


  public function handle( $requestData = null ): array {
    $queuedSince = $this->normalizedSuggestionsQuery->getQueuedSince();
    $user        = $this->userQuery->getCurrent();
    $userId      = $user ? $user->getId() : null;

    return [
      'success' => true,
      'data'    => [
        'pending'           => $this->normalizedSuggestionsQuery->getCount(),
        'queued'            => $this->normalizedSuggestionsQuery->getQueued(),
        'queuedSince'       => $queuedSince,
        'reminderDismissed' => $this->isReminderDismissed( $userId, $queuedSince ),
        'pendingDismissed'  => $this->isPendingDismissed( $userId ),
      ]
    ];
  }


  private function isReminderDismissed( $userId, $queuedSince ): bool {
    if ( $userId === null || ! is_string( $queuedSince ) || $queuedSince === '' ) {
      return false;
    }

    $noticeId = DismissQueuedRetranslationReminderController::noticeId( $queuedSince );

    return ! empty( $this->dismissedNoticesQuery->getDismissedByUser( $userId, [ $noticeId ] ) );
  }


  private function isPendingDismissed( $userId ): bool {
    if ( $userId === null ) {
      return false;
    }

    $candidates = DismissPendingSuggestionsNudgeController::dismissedNoticeIdsToCheck();

    return ! empty( $this->dismissedNoticesQuery->getDismissedByUser( $userId, $candidates ) );
  }


}
