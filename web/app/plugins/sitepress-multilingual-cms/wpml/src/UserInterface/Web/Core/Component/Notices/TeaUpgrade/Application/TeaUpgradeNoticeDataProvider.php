<?php

namespace WPML\UserInterface\Web\Core\Component\Notices\TeaUpgrade\Application;

use WPML\Core\Component\Communication\Application\Query\DismissedNoticesQuery;
use WPML\Core\Port\PluginInterface;
use WPML\Core\SharedKernel\Component\User\Application\Query\UserQueryInterface;

class TeaUpgradeNoticeDataProvider {

  const NOTICE_ID = 'tea-upgrade-notice';

  private $plugin;

  private $userQuery;

  private $dismissedNoticesQuery;

  private $noticeInstanceIdProvider;


  public function __construct(
    PluginInterface $plugin,
    UserQueryInterface $userQuery,
    DismissedNoticesQuery $dismissedNoticesQuery,
    NoticeInstanceIdProvider $noticeInstanceIdProvider
  ) {
    $this->plugin                   = $plugin;
    $this->userQuery                = $userQuery;
    $this->dismissedNoticesQuery    = $dismissedNoticesQuery;
    $this->noticeInstanceIdProvider = $noticeInstanceIdProvider;
  }


  public function get(): array {
    $isEligible = $this->isEligible();

    return [
      'isEligible'       => $isEligible,
      'noticeId'         => self::NOTICE_ID,
      'noticeInstanceId' => $isEligible ? $this->noticeInstanceIdProvider->get() : '',
      'scenario'         => null,
    ];
  }


  public function isEligible(): bool {
    $startVersion = $this->plugin->getVersionWhenSetupRanWithoutSuffix();
    $isEligible   = $this->plugin->isSetupComplete()
                    && $startVersion !== ''
                    && (int) $startVersion < 5;

    if ( ! $isEligible ) {
      return false;
    }

    $user = $this->userQuery->getCurrent();
    if ( ! $user ) {
      return true;
    }

    $dismissed = $this->dismissedNoticesQuery->getDismissedByUser(
      $user->getId(),
      [ self::NOTICE_ID ]
    );

    return empty( $dismissed );
  }


}
