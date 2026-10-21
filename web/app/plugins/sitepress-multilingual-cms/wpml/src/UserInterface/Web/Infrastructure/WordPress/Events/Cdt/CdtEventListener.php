<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Events\Cdt;

use WPML\Core\Component\Cdt\Application\Service\EventRaiser;

class CdtEventListener {

  private $raiser;


  public function __construct( EventRaiser $raiser ) {
    $this->raiser = $raiser;
  }


  public function onSetupCompleted(): void {
    try {
      $this->raiser->onSetupCompleted( time() );
    } catch ( \Throwable $e ) {
      return;
    }
  }


  public function onTeaToggled( $enabled ): void {
    try {
      $this->raiser->onTeaToggled( (bool) $enabled, time() );
    } catch ( \Throwable $e ) {
      return;
    }
  }


  public function onDeactivated(): void {
    try {
      $this->raiser->onDeactivated( time() );
    } catch ( \Throwable $e ) {
      return;
    }
  }


}
