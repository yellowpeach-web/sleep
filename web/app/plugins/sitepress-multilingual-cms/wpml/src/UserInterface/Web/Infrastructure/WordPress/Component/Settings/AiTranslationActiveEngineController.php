<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;

use WPML\Core\Component\ATE\Application\Service\ActiveEngineQueryInterface;
use WPML\Core\Component\ATE\Application\Service\Dto\ActiveEngineResolutionDto;
use WPML\Core\Component\ATE\Application\Service\EnginesServiceInterface;
use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\TM\ATE\ClonedSites\ReconnectState;

class AiTranslationActiveEngineController implements EndpointInterface {

  const ROUTE = '/wpml/v1/get-active-engine';

  const PARAM_FRESH = 'fresh';

  private $activeEngineQuery;

  private $enginesService;


  public function __construct(
    ActiveEngineQueryInterface $activeEngineQuery,
    EnginesServiceInterface $enginesService
  ) {
    $this->activeEngineQuery = $activeEngineQuery;
    $this->enginesService    = $enginesService;
  }


  public function handle( $requestData = null ): array {
    if ( self::wantsFresh( $requestData ) ) {
      ReconnectState::makeDue();
      $this->enginesService->flushCache();
    }

    $resolution = $this->activeEngineQuery->resolve();
    $engine     = $resolution->getEngine();

    do_action( 'wpml_ai_translation_settings_rendered', $engine !== null );

    return [
      'status' => true,
      'state'  => self::stateOf( $resolution ),
      'engine' => $engine === null
        ? null
        : [
          'codeName'   => $engine->getCodeName(),
          'formalName' => $engine->getFormalName(),
        ],
    ];
  }


  private static function wantsFresh( $requestData ): bool {
    if ( ! is_array( $requestData ) || ! isset( $requestData[ self::PARAM_FRESH ] ) ) {
      return false;
    }

    $value = $requestData[ self::PARAM_FRESH ];

    return $value === true || $value === 1 || $value === '1' || $value === 'true';
  }


  private static function stateOf( ActiveEngineResolutionDto $resolution ): string {
    if ( $resolution->isUnavailable() ) {
      return ActiveEngineResolutionDto::STATE_UNAVAILABLE;
    }

    if ( $resolution->isNoneEnabled() ) {
      return ActiveEngineResolutionDto::STATE_NONE_ENABLED;
    }

    return ActiveEngineResolutionDto::STATE_ACTIVE;
  }


}
