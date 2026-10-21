<?php

namespace WPML\Core\Component\Cdt\Application\Service;

class EngineChange {

  private $engineOption;

  private $raiser;


  public function __construct( EngineOption $engineOption, EventRaiser $raiser ) {
    $this->engineOption = $engineOption;
    $this->raiser       = $raiser;
  }


  public function detect( int $now ): bool {
    if ( ! $this->engineOption->onEnginesUpdated() ) {
      return false;
    }

    if ( ! $this->engineOption->isPreviousEngineKnown() ) {
      return true;
    }

    $this->raiser->onEngineChanged(
      $this->engineOption->getPreviousEngine(),
      $this->engineOption->getCurrentEngine(),
      $now
    );

    return true;
  }


}
