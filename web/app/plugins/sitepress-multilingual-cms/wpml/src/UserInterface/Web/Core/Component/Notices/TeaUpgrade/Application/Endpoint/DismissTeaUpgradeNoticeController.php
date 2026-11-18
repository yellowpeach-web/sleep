<?php

namespace WPML\UserInterface\Web\Core\Component\Notices\TeaUpgrade\Application\Endpoint;

use WPML\Core\Component\Communication\Application\Service\DismissNoticeService;
use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\Core\SharedKernel\Component\User\Application\Query\UserQueryInterface;
use WPML\UserInterface\Web\Core\Component\Notices\TeaUpgrade\Application\TeaUpgradeDismissedTracker;
use WPML\UserInterface\Web\Core\Component\Notices\TeaUpgrade\Application\TeaUpgradeFunnelProps;
use WPML\UserInterface\Web\Core\Component\Notices\TeaUpgrade\Application\TeaUpgradeNoticeDataProvider;

class DismissTeaUpgradeNoticeController implements EndpointInterface {

  const COMPACT_SURFACES = [
    TeaUpgradeFunnelProps::SURFACE_WP_DASHBOARD,
    TeaUpgradeFunnelProps::SURFACE_PAYMENTS_TAB,
    TeaUpgradeFunnelProps::SURFACE_AI_SETTINGS,
  ];

  private $noticeDataProvider;

  private $dismissNoticeService;

  private $userQuery;

  private $dismissedTracker;


  public function __construct(
    TeaUpgradeNoticeDataProvider $noticeDataProvider,
    DismissNoticeService $dismissNoticeService,
    UserQueryInterface $userQuery,
    TeaUpgradeDismissedTracker $dismissedTracker
  ) {
    $this->noticeDataProvider   = $noticeDataProvider;
    $this->dismissNoticeService = $dismissNoticeService;
    $this->userQuery            = $userQuery;
    $this->dismissedTracker     = $dismissedTracker;
  }


  public function handle( $requestData = null ): array {
    $surface = isset( $requestData['surface'] ) && is_string( $requestData['surface'] )
      ? $requestData['surface']
      : '';
    if ( ! in_array( $surface, self::COMPACT_SURFACES, true ) ) {
      return [ 'success' => false ];
    }

    $user = $this->userQuery->getCurrent();
    if ( ! $user ) {
      return [ 'success' => false ];
    }

    $noticeData = $this->noticeDataProvider->get();
    if ( ! $noticeData['isEligible'] ) {
      return [ 'success' => true ];
    }

    $this->dismissNoticeService->dismissPerUser(
      TeaUpgradeNoticeDataProvider::NOTICE_ID,
      $user->getId()
    );
    $this->dismissedTracker->trackDismissed( $surface, $noticeData );

    return [ 'success' => true ];
  }


}
