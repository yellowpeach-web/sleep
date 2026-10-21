<?php

namespace WPML\Core\Component\ATE\Application\Service\Dto;

class ActiveEngineResolutionDto {

  const STATE_ACTIVE       = 'active';
  const STATE_NONE_ENABLED = 'none-enabled';
  const STATE_UNAVAILABLE  = 'unavailable';

  private $state;

  private $engine;


  private function __construct( string $state, ?EngineDto $engine = null ) {
    $this->state  = $state;
    $this->engine = $engine;
  }


  public static function active( EngineDto $engine ): self {
    return new self( self::STATE_ACTIVE, $engine );
  }


  public static function noneEnabled(): self {
    return new self( self::STATE_NONE_ENABLED );
  }


  public static function unavailable(): self {
    return new self( self::STATE_UNAVAILABLE );
  }


  public function getEngine(): ?EngineDto {
    return $this->engine;
  }


  public function isNoneEnabled(): bool {
    return $this->state === self::STATE_NONE_ENABLED;
  }


  public function isUnavailable(): bool {
    return $this->state === self::STATE_UNAVAILABLE;
  }


}
