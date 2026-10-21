<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Events\Cdt;

use WPML\Core\Component\Cdt\Application\Service\EventSender;

class EventSenderListener {

  private $sender;


  public function __construct( EventSender $sender ) {
    $this->sender = $sender;
  }


  public function run(): void {
    $this->sender->sendQueued( time() );
  }


}
