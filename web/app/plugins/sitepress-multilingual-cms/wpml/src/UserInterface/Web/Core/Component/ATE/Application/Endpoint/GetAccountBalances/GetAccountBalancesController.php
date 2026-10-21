<?php

namespace WPML\UserInterface\Web\Core\Component\ATE\Application\Endpoint\GetAccountBalances;

use WPML\Core\Component\ATE\Application\Query\AccountException;
use WPML\Core\Component\ATE\Application\Query\AccountInterface;
use WPML\Core\Port\Endpoint\EndpointInterface;

class GetAccountBalancesController implements EndpointInterface {

  const ERROR_LOOKUP_FAILED = 'account_balances_unavailable';

  private $ateAccount;


  public function __construct( AccountInterface $ateAccount ) {
    $this->ateAccount = $ateAccount;
  }


  public function handle( $requestData = null ): array {
    $allowCached = is_array( $requestData ) && ! empty( $requestData['allowCached'] );

    $forceProbe = is_array( $requestData ) && ! empty( $requestData['forceProbe'] );

    try {
      $accountBalances = $this->ateAccount->getAccountBalances( $allowCached, $forceProbe );
      return [
        'success' => true,
        'data'    => [
          'account_balance' => $accountBalances->getAccountBalance(),
          'redirect_url'    => $accountBalances->getRedirectUrl(),
          'site'            => $accountBalances->getSite(),
          'token'           => $accountBalances->getToken(),
          'raise_cap_url'   => $accountBalances->getRaiseCapUrl(),
        ]
      ];
    } catch ( AccountException $e ) {
      return [
        'success' => false,
        'error'   => self::ERROR_LOOKUP_FAILED,
        'message' => $e->getMessage(),
      ];
    }
  }


}
