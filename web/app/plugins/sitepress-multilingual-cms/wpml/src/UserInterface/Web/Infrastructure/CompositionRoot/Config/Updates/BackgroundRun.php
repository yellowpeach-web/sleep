<?php

namespace WPML\UserInterface\Web\Infrastructure\CompositionRoot\Config\Updates;

class BackgroundRun {
  const HOOK = 'wpml_updates_continue';

  private $repository;


  public function __construct( Repository $repository ) {
    $this->repository = $repository;
  }


  public function isRunning() {
    return doing_action( self::HOOK );
  }


  public function isFrontEnd() {
    return ! is_admin();
  }


  public function arm( $allUpdates ) {
    $lazy = array_filter(
      $allUpdates,
      function ( Update $update ) {
        return $update->lazyLoad();
      }
    );

    if ( $this->repository->getUpdatesToPerform( $lazy ) ) {
      $this->scheduleIn( 0 );
      return;
    }

    foreach ( $lazy as $update ) {
      if ( $this->repository->isUnfinished( $update ) ) {
        $this->scheduleIn( Repository::RETRY_DELAY_SECONDS );
        return;
      }
    }

    $this->clear();
  }


  private function scheduleIn( $seconds ) {
    $at   = $this->now() + $seconds;
    $next = $this->nextScheduled();

    if ( $next !== null && $next <= $at ) {
      return;
    }

    if ( $next !== null ) {
      $this->unschedule( $next );
    }

    $this->scheduleAt( $at );
  }


  private function clear() {
    $next = $this->nextScheduled();
    if ( $next !== null ) {
      $this->unschedule( $next );
    }
  }


  protected function now() {
    return time();
  }


  protected function nextScheduled() {
    $next = wp_next_scheduled( self::HOOK );

    return is_int( $next ) ? $next : null;
  }


  protected function scheduleAt( $timestamp ) {
    wp_schedule_single_event( $timestamp, self::HOOK );
  }


  protected function unschedule( $timestamp ) {
    wp_unschedule_event( $timestamp, self::HOOK );
  }


}
