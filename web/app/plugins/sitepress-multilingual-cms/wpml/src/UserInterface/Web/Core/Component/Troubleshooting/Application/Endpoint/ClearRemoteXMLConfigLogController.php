<?php

namespace WPML\UserInterface\Web\Core\Component\Troubleshooting\Application\Endpoint;

use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML_Config_Update_Log;


class ClearRemoteXMLConfigLogController implements EndpointInterface {

  private $log;


  public function __construct( WPML_Config_Update_Log $log ) {
    $this->log = $log;
  }


  public function handle( $requestData = null ): array {
    $this->log->clear();

    return [
      'success' => true,
      'entries' => 0,
    ];
  }


}
