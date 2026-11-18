<?php

namespace WPML\UserInterface\Web\Core\Component\Troubleshooting\Application\Endpoint;

use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\TM\ATE\ClonedSites\AutoMigration\Handler;
use WPML\TM\ATE\ClonedSites\ReconnectState;
use WPML\TM\ATE\ClonedSites\SecondaryDomains;

class EnableAliasDomainController implements EndpointInterface {

  private $secondaryDomains;


  public function __construct( SecondaryDomains $secondaryDomains ) {
    $this->secondaryDomains = $secondaryDomains;
  }


  public function handle( $requestData = null ): array {
    $urls            = ReconnectState::urls();
    $aliasUrl        = $urls['new_url'];
    $originalSiteUrl = $urls['old_url'];

    if ( '' === $aliasUrl || '' === $originalSiteUrl ) {
      return [
        'success' => false,
        'message' => __( 'There is no pending alias domain to enable.', 'wpml' ),
      ];
    }

    if ( $this->secondaryDomains->isAnotherSiteOfTheNetwork( $originalSiteUrl ) ) {
      return [
        'success' => false,
        /* translators: Error shown when a subsite of a WordPress network is declared to be another subsite of the same network under a different address. */
        'message' => __( 'This address belongs to another site in this network, so this site cannot use it as its original address. A site created by duplicating another site connects to our translation system as a site of its own: reset its alias domains under WPML → Support and let it connect again.', 'wpml' ),
      ];
    }

    $aliasDomains = $this->secondaryDomains->add( $aliasUrl, $originalSiteUrl );

    ReconnectState::resolveAsMove();

    Handler::clearMigrationData();

    return [
      'success'     => true,
      'aliasDomain' => [
        'originalSiteUrl' => $originalSiteUrl,
        'aliasDomains'    => $aliasDomains,
      ],
    ];
  }


}
