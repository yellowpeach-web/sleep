<?php

namespace WPML\Core\Component\ATE\Application\Service;

use WPML\Core\Component\ATE\Application\Service\Dto\EngineDto;

class PtcEngineStatus {

  const PTC_ENGINE_SLUG = 'llm';

  private $enginesService;


  public function __construct( EnginesServiceInterface $enginesService ) {
    $this->enginesService = $enginesService;
  }


  public function isDefaultEngine(): bool {
    $engine = $this->getFirstEnabledEngine();

    return $engine !== null && $engine->getCodeName() === self::PTC_ENGINE_SLUG;
  }


  public function isDefaultEngineFromList( array $engines ): bool {
    foreach ( $engines as $engine ) {
      if ( $engine->isEnabled() ) {
        return $engine->getCodeName() === self::PTC_ENGINE_SLUG;
      }
    }

    return false;
  }


  private function getFirstEnabledEngine(): ?EngineDto {
    try {
      foreach ( $this->enginesService->getListWithoutFormalities() as $engine ) {
        if ( $engine->isEnabled() ) {
          return $engine;
        }
      }
    } catch ( EngineServiceException $e ) {
      return null;
    }

    return null;
  }


}
