<?php

namespace WPML\Core\Component\Cdt\Application\Service;

use WPML\Core\Component\Cdt\Application\Query\RequestContextQueryInterface;
use WPML\Core\Component\Cdt\Application\Query\SiteIdentityQueryInterface;
use WPML\Core\Component\Cdt\Application\Repository\CdtStoreInterface;
use WPML\Core\Component\Cdt\Domain\CheckinSchedule;

class CheckinTrigger {

  private $requestContext;

  private $store;

  private $checkinRequest;

  private $siteIdentity;

  private $subscriptionFetch;

  private $asked = false;


  public function __construct(
    RequestContextQueryInterface $requestContext,
    CdtStoreInterface $store,
    CheckinRequest $checkinRequest,
    SiteIdentityQueryInterface $siteIdentity,
    SubscriptionFetchInterface $subscriptionFetch
  ) {
    $this->requestContext    = $requestContext;
    $this->store             = $store;
    $this->checkinRequest    = $checkinRequest;
    $this->siteIdentity      = $siteIdentity;
    $this->subscriptionFetch = $subscriptionFetch;
  }


  public function run( int $now ): bool {
    if ( $this->asked ) {
      return false;
    }

    if ( ! $this->requestContext->isAdminPageLoad() ) {
      return false;
    }

    if ( $this->checkinRequest->getSentAt() !== null ) {
      return false;
    }

    if ( ! CheckinSchedule::isDue( $this->store->getLastCheckinAt(), $now ) ) {
      return false;
    }

    if ( ! $this->subscriptionFetch->isAvailable() || $this->siteIdentity->getSiteKey() === null ) {
      return false;
    }

    $this->asked = true;
    $this->subscriptionFetch->fetch();

    return true;
  }


}
