<?php

namespace WPML\UserInterface\Web\Core\Component\Notices\TeaUpgrade\Application;

use WPML\Core\Component\ATE\Application\Query\WebsiteContextException;
use WPML\Core\Component\ATE\Application\Query\WebsiteContextQueryInterface;
use WPML\Core\Component\ATE\Application\Service\ActiveEngineQueryInterface;
use WPML\Core\Component\ATE\Application\Service\PtcEngineStatus;
use WPML\Core\Component\Translation\Application\Query\TranslateEverythingEnabledQueryInterface;
use WPML\UserInterface\Web\Core\Component\Notices\TeaUpgrade\Application\Dto\ScenarioDto;

class TeaUpgradeScenarioQuery {

  private $activeEngineQuery;

  private $websiteContextQuery;

  private $translateEverythingEnabledQuery;


  public function __construct(
    ActiveEngineQueryInterface $activeEngineQuery,
    WebsiteContextQueryInterface $websiteContextQuery,
    TranslateEverythingEnabledQueryInterface $translateEverythingEnabledQuery
  ) {
    $this->activeEngineQuery               = $activeEngineQuery;
    $this->websiteContextQuery             = $websiteContextQuery;
    $this->translateEverythingEnabledQuery = $translateEverythingEnabledQuery;
  }


  public function get(): ScenarioDto {
    return new ScenarioDto(
      $this->resolveEngineCodeName(),
      $this->isDescriptionPresent(),
      $this->isTeaOn()
    );
  }


  private function resolveEngineCodeName(): string {
    $engine = $this->activeEngineQuery->get();

    return $engine !== null ? $engine->getCodeName() : PtcEngineStatus::PTC_ENGINE_SLUG;
  }


  private function isDescriptionPresent(): bool {
    try {
      return $this->websiteContextQuery->isContextPresent();
    } catch ( WebsiteContextException $e ) {
      return false;
    }
  }


  private function isTeaOn(): bool {
    return $this->translateEverythingEnabledQuery->isEnabled();
  }


}
