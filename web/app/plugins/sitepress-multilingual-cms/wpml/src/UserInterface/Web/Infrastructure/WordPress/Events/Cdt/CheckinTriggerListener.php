<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Events\Cdt;

use WPML\Core\Component\Cdt\Application\Service\CheckinTrigger;

class CheckinTriggerListener {

  private $trigger;


  public function __construct( CheckinTrigger $trigger ) {
    $this->trigger = $trigger;
  }


  public function onAdminInit() {
    try {
      $this->trigger->run( time() );
    } catch ( \Throwable $e ) {
      return;
    }
  }


}
