<?php

namespace WPML\Legacy\Component\ATE\Application\Service;

use WPML\Core\Component\ATE\Application\Service\ActiveEngineQueryInterface;
use WPML\Core\Component\ATE\Application\Service\Dto\ActiveEngineResolutionDto;
use WPML\Core\Component\ATE\Application\Service\Dto\EngineDto;

use WPML\TM\ATE\API\CacheStorage\Transient;
use WPML\TM\ATE\API\CachedAMSAPI;
use function WPML\Container\make;

class ActiveEngineQuery implements ActiveEngineQueryInterface {

  private $amsApi;


  public function __construct( $amsApi = null ) {
    $this->amsApi = $amsApi;
  }


  public function resolve(): ActiveEngineResolutionDto {
    try {
      $engines = $this->amsApi()->get_translation_engines();
    } catch ( \Throwable $e ) {
      return ActiveEngineResolutionDto::unavailable();
    }

    if ( is_wp_error( $engines ) || ! is_array( $engines ) ) {
      return ActiveEngineResolutionDto::unavailable();
    }

    if ( ! array_key_exists( 'list', $engines ) || ! is_array( $engines['list'] ) ) {
      return ActiveEngineResolutionDto::unavailable();
    }

    foreach ( $engines['list'] as $engine ) {
      if (
        ! is_array( $engine )
        || ! array_key_exists( 'engine', $engine )
        || ! is_string( $engine['engine'] )
        || empty( $engine['enabled'] )
      ) {
        continue;
      }

      return ActiveEngineResolutionDto::active(
        new EngineDto(
          $engine['engine'],
          is_string( $engine['formal_name'] ?? null ) ? $engine['formal_name'] : $engine['engine'],
          (int) ( $engine['cost'] ?? 0 ),
          true,
          (bool) ( $engine['formality_available'] ?? false )
        )
      );
    }

    return ActiveEngineResolutionDto::noneEnabled();
  }


  public function get(): ?EngineDto {
    return $this->resolve()->getEngine();
  }


  private function amsApi() {
    if ( $this->amsApi === null ) {
      $this->amsApi = new CachedAMSAPI(
        make( \WPML_TM_AMS_API::class ),
        new Transient()
      );
    }

    return $this->amsApi;
  }


}
