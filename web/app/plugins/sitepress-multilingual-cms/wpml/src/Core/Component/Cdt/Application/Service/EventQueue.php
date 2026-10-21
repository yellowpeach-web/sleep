<?php

namespace WPML\Core\Component\Cdt\Application\Service;

use WPML\Core\Component\Cdt\Application\Repository\CdtStoreInterface;
use WPML\Core\Component\Cdt\Domain\CdtEvent;

class EventQueue {

  const HOOK               = 'wpml_cdt_send_events';
  const MAX_ENVELOPES      = 10;
  const SEND_DELAY_SECONDS = 2;

  private $store;

  private $scheduler;

  private $reported = 0;


  public function __construct( CdtStoreInterface $store, CronSchedulerInterface $scheduler ) {
    $this->store     = $store;
    $this->scheduler = $scheduler;
  }


  public function push( CdtEvent $event, int $now ): void {
    $events            = $this->store->getEvents( true );
    $events['queue'][] = [ 'event' => $event->toArray(), 'attempts' => 0 ];

    $over = count( $events['queue'] ) - self::MAX_ENVELOPES;
    if ( $over > 0 ) {
      $events['queue']    = array_slice( $events['queue'], $over );
      $events['dropped'] += $over;
    }

    $this->store->saveEvents( $events );
    $this->scheduler->scheduleOnceAt( self::HOOK, $now + self::SEND_DELAY_SECONDS );
  }


  public function counts(): array {
    $events = $this->store->getEvents();

    return [ 'pending' => count( $events['queue'] ), 'dropped' => $events['dropped'] ];
  }


  public function markReported( int $dropped ): void {
    $this->reported = max( 0, $dropped );
  }


  public function confirmReported(): void {
    if ( $this->reported === 0 ) {
      return;
    }

    $events            = $this->store->getEvents( true );
    $events['dropped'] = max( 0, $events['dropped'] - $this->reported );
    $this->reported    = 0;
    $this->store->saveEvents( $events );
  }


}
