<?php

namespace WPML\Core\Component\Cdt\Application\Service;

use WPML\Core\Component\ATE\Application\Service\ActiveEngineQueryInterface;
use WPML\Core\Component\ATE\Application\Service\Dto\ActiveEngineResolutionDto;
use WPML\Core\Component\ATE\Application\Service\PtcEngineStatus;
use WPML\Core\Component\Cdt\Application\Repository\CdtStoreInterface;

class EngineOption {

  const PTC = 'ptc';

  private $engines;

  private $store;

  private $previous = null;

  private $current = null;

  private $previousKnown = false;


  public function __construct( ActiveEngineQueryInterface $engines, CdtStoreInterface $store ) {
    $this->engines = $engines;
    $this->store   = $store;
  }


  public function onEnginesUpdated(): bool {
    $resolved = $this->resolve();
    if ( ! $resolved['known'] ) {
      return false;
    }

    $this->previous      = $this->store->getEngine();
    $this->previousKnown = $this->store->isEngineKnown();
    $this->current       = $resolved['engine'];
    $this->store->saveEngine( $resolved['engine'] );

    return true;
  }


  public function getCurrentEngine(): ?string {
    return $this->current;
  }


  public function getPreviousEngine(): ?string {
    return $this->previous;
  }


  public function isPreviousEngineKnown(): bool {
    return $this->previousKnown;
  }


  public function backfill(): void {
    if ( $this->store->hasEngine() ) {
      return;
    }

    $resolved = $this->resolve();
    if ( $resolved['known'] ) {
      $this->store->saveEngine( $resolved['engine'] );
    } else {
      $this->store->saveEngineUnknown();
    }
  }


  private function resolve(): array {
    try {
      $resolution = $this->engines->resolve();
    } catch ( \Throwable $e ) {
      return [ 'known' => false, 'engine' => null ];
    }

    return self::nameOf( $resolution );
  }


  public static function nameOf( ActiveEngineResolutionDto $resolution ): array {
    if ( $resolution->isNoneEnabled() ) {
      return [ 'known' => true, 'engine' => null ];
    }

    $engine = $resolution->getEngine();
    if ( $engine === null ) {
      return [ 'known' => false, 'engine' => null ];
    }

    $code = $engine->getCodeName();

    return [
      'known'  => true,
      'engine' => $code === PtcEngineStatus::PTC_ENGINE_SLUG ? self::PTC : substr( $code, 0, 32 ),
    ];
  }


}
