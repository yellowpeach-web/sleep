<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Events\Cdt;

use WPML\Core\Component\ATE\Application\Service\EnginesServiceInterface;
use WPML\Core\Component\Cdt\Application\Service\EngineChange;
use WPML\Core\Component\Cdt\Application\Service\HealthRefresh;
use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;

class HealthRefreshListener {

  private $healthRefresh;

  private $engines;

  private $engineChange;


  public function __construct( HealthRefresh $healthRefresh, EnginesServiceInterface $engines, EngineChange $engineChange ) {
    $this->healthRefresh = $healthRefresh;
    $this->engines       = $engines;
    $this->engineChange  = $engineChange;
  }


  public function run(): void {
    try {
      $this->healthRefresh->refresh( time() );
    } catch ( DatabaseErrorException $e ) {
    }

    try {
      $this->engines->flushEnginesCache();
      $this->engineChange->detect( time() );
    } catch ( \Throwable $e ) {
      return;
    }
  }


}
