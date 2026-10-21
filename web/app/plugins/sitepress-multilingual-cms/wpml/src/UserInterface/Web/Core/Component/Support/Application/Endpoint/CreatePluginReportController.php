<?php

namespace WPML\UserInterface\Web\Core\Component\Support\Application\Endpoint;

use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\Core\SharedKernel\Component\Installer\Application\Query\WpmlSiteKeyQueryInterface;
use WPML\Core\SharedKernel\Component\Installer\Application\Query\WpmlSubscriptionQueryInterface;
use WPML\Core\SharedKernel\Component\Support\Application\Query\DebugInformationQueryInterface;
use WPML\Core\SharedKernel\Component\WpmlOrgClient\Domain\Api\Endpoints\PluginReportInterface;

class CreatePluginReportController implements EndpointInterface {

  private $siteKeyQuery;

  private $subscriptionQuery;

  private $debugInformationQuery;

  private $pluginReport;


  public function __construct(
    WpmlSiteKeyQueryInterface $siteKeyQuery,
    WpmlSubscriptionQueryInterface $subscriptionQuery,
    DebugInformationQueryInterface $debugInformationQuery,
    PluginReportInterface $pluginReport
  ) {
    $this->siteKeyQuery          = $siteKeyQuery;
    $this->subscriptionQuery     = $subscriptionQuery;
    $this->debugInformationQuery = $debugInformationQuery;
    $this->pluginReport          = $pluginReport;
  }


  public function handle( $requestData = null ): array {
    $siteKey = $this->siteKeyQuery->get();

    if ( ! is_string( $siteKey ) || $siteKey === '' ) {
      return [
        'success' => false,
        'data'    => [ 'error' => 'not_registered' ],
      ];
    }

    if ( ! $this->subscriptionQuery->isValid() ) {
      return [
        'success' => false,
        'data'    => [ 'error' => 'subscription_expired' ],
      ];
    }

    $result = $this->pluginReport->run(
      $siteKey,
      $this->debugInformationQuery->get(),
      true
    );

    if ( ! $result['success'] || $result['reportUrl'] === '' ) {
      $expired = $result['errorCode'] === 'subscription_expired';

      return [
        'success' => false,
        'data'    => [ 'error' => $expired ? 'subscription_expired' : 'request_failed' ],
      ];
    }

    return [
      'success' => true,
      'data'    => [ 'url' => $result['reportUrl'] ],
    ];
  }


}
