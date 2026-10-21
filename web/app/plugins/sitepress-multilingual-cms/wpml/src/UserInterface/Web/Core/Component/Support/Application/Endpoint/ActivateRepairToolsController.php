<?php

namespace WPML\UserInterface\Web\Core\Component\Support\Application\Endpoint;

use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\Core\SharedKernel\Component\Support\Application\Repository\RepairToolsPluginInterface;

class ActivateRepairToolsController implements EndpointInterface {

  private $plugin;


  public function __construct( RepairToolsPluginInterface $plugin ) {
    $this->plugin = $plugin;
  }


  public function handle( $requestData = null ): array {
    if ( ! $this->plugin->isInstalled() ) {
      return [
        'success' => false,
        'data'    => [ 'error' => 'not_installed' ],
      ];
    }

    if ( $this->plugin->isActive() ) {
      return [
        'success' => true,
        'data'    => [ 'activated' => false, 'alreadyActive' => true ],
      ];
    }

    if ( ! $this->plugin->activate() ) {
      return [
        'success' => false,
        'data'    => [ 'error' => 'activation_failed' ],
      ];
    }

    return [
      'success' => true,
      'data'    => [ 'activated' => true, 'alreadyActive' => false ],
    ];
  }
}
