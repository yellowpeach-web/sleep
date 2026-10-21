<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Events\Cdt;

use WPML\Core\Component\Cdt\Application\Service\EngineChange;

class EngineOptionListener {

  private $engineChange;


  public function __construct( EngineChange $engineChange ) {
    $this->engineChange = $engineChange;
  }


  public function onEnginesUpdated(): void {
    try {
      $this->engineChange->detect( time() );
    } catch ( \Throwable $e ) {
      return;
    }
  }


}
