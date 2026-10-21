<?php

namespace WPML\Legacy\Component\Translation\Application\Query;

use WPML\Core\Component\Translation\Application\Query\TranslateEverythingEnabledQueryInterface;
use WPML\Core\Component\Translation\Application\Repository\SettingsRepository;

class TranslateEverythingEnabledQuery implements TranslateEverythingEnabledQueryInterface {

  private $settingsRepository;


  public function __construct( SettingsRepository $settingsRepository ) {
    $this->settingsRepository = $settingsRepository;
  }


  public function isEnabled(): bool {
    return $this->settingsRepository->getSettings()->getTranslateEverything()->isEnabled();
  }


}
