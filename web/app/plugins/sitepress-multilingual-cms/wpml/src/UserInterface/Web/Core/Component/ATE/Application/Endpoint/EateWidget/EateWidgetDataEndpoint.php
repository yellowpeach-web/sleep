<?php

namespace WPML\UserInterface\Web\Core\Component\ATE\Application\Endpoint\EateWidget;

use WPML\Core\Port\Endpoint\EndpointInterface;

class EateWidgetDataEndpoint implements EndpointInterface {

  private $eateWidget;


  public function __construct( EateWidgetInterface $eateWidget ) {
    $this->eateWidget = $eateWidget;
  }


  public function handle( $requestData = null ): array {
    try {
      $eateWidgetData = $this->eateWidget->getData();
      return [
        'success' => true,
        'data'    => $eateWidgetData,
      ];
    } catch ( \Throwable $e ) {
      return [
        'success' => false,
        'message' => 'Failed to fetch EATE widget data.',
      ];
    }
  }


}
