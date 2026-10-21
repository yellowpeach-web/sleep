<?php

namespace WPML\Legacy\Component\ATE\Application\Service;

use WPML\Core\SharedKernel\Component\ATE\Application\Service\AteActivationInterface;

class AteActivation implements AteActivationInterface {

  private $enableAteEndpoint;


  public function __construct( \WPML\TM\ATE\AutoTranslate\Endpoint\EnableATE $enableAteEndpoint ) {
    $this->enableAteEndpoint = $enableAteEndpoint;
  }


  public function activate(): bool {
    if ( \WPML_TM_ATE_Status::is_active() ) {
      return true;
    }

    try {
      $this->enableAteEndpoint->run( wpml_collect( [] ) );
    } catch ( \Throwable $e ) {
      unset( $e );
    }

    return \WPML_TM_ATE_Status::is_active();
  }


}
