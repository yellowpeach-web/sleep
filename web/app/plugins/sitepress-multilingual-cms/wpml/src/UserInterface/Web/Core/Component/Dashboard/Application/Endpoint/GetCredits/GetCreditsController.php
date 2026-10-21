<?php

namespace WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\GetCredits;

use WPML\Core\Component\ATE\Application\Query\AccountException;
use WPML\Core\Component\ATE\Application\Query\AccountInterface;
use WPML\Core\Component\ATE\Application\Service\CreditsService;
use WPML\Core\Port\Endpoint\EndpointInterface;

class GetCreditsController implements EndpointInterface {

  private $ateAccount;

  private $creditsService;


  public function __construct( AccountInterface $ateAccount, CreditsService $creditsService ) {
    $this->ateAccount = $ateAccount;
    $this->creditsService = $creditsService;
  }


  public function handle( $requestData = null ): array {
    $allowCached = is_array( $requestData ) && ! empty( $requestData['allowCached'] );

    $forceProbe = is_array( $requestData ) && ! empty( $requestData['forceProbe'] );

    try {
      $credits = $this->ateAccount->getCredits( $allowCached, $forceProbe );
    } catch ( AccountException $e ) {
      $failure = [
        'success' => false,
        'message' => $e->getMessage(),
      ];

      if ( $e->getReason() !== '' ) {
        $failure['reason'] = $e->getReason();
      }

      return $failure;
    }

    return [
      'success' => true,
      'data'    => [
        'available_balance'         => $credits->getAvailableBalance(),
        'payAsYouGo'                => $credits->getPayAsYouGo(),
        'activeSubscription'        => $credits->getActiveSubscription(),
        'payAsYouGoSubscription'    => $credits->getPayAsYouGo() || $credits->getActiveSubscription(),
        'totalCreditsDeposited'     => $credits->getTotalCreditsDeposited(),
        'totalCreditsSpent'         => $credits->getTotalCreditsSpent(),
        'subscriptionUsage'         => $credits->getSubscriptionUsage(),
        'subscriptionDebt'          => $credits->getSubscriptionDebt(),
        'subscriptionMaxLimit'      => $credits->getSubscriptionMaxLimit(),
        'creditsInProgress'         => $this->creditsService->getCreditsInProgress()->getCount(),
        'has_software'              => $credits->getHasSoftware(),
        'billingContactMissing'     => $credits->getBillingContactMissing(),
        'billingContactUnreachable' => $credits->getBillingContactUnreachable(),
        'siteSpendCap'              => $credits->getSiteSpendCap(),
        'debtorBlock'               => $credits->getDebtorBlock(),
      ]
    ];
  }


}
