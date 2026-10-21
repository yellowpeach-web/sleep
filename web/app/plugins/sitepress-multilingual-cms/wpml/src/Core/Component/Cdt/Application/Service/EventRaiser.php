<?php

namespace WPML\Core\Component\Cdt\Application\Service;

use WPML\Core\Component\Cdt\Application\Query\SiteIdentityQueryInterface;
use WPML\Core\Component\Cdt\Domain\CdtEvent;

class EventRaiser {

  private $identity;

  private $queue;

  private $sender;


  public function __construct( SiteIdentityQueryInterface $identity, EventQueue $queue, EventSender $sender ) {
    $this->identity = $identity;
    $this->queue    = $queue;
    $this->sender   = $sender;
  }


  public function onSetupCompleted( int $now ): void {
    $this->queueEvent( CdtEvent::SETUP_COMPLETED, $now, null, null );
  }


  public function onTeaToggled( bool $enabled, int $now ): void {
    $this->queueEvent( CdtEvent::TEA_TOGGLED, $now, $enabled, null );
  }


  public function onEngineChanged( ?string $oldEngine, ?string $newEngine, int $now ): void {
    if ( $oldEngine === $newEngine ) {
      return;
    }

    $this->queueEvent( CdtEvent::ENGINE_CHANGED, $now, $newEngine, $oldEngine );
  }


  public function onDeactivated( int $now ): void {
    $siteKey = $this->identity->getSiteKey();
    if ( $siteKey === null ) {
      return;
    }

    $this->sender->sendNow( new CdtEvent( CdtEvent::WPML_DEACTIVATED, $now, null, null, $siteKey ), $now );
  }


  private function queueEvent( string $type, int $now, $value, $oldValue ): void {
    $siteKey = $this->identity->getSiteKey();
    if ( $siteKey === null ) {
      return;
    }

    $this->queue->push( new CdtEvent( $type, $now, $value, $oldValue, $siteKey ), $now );
  }


}
