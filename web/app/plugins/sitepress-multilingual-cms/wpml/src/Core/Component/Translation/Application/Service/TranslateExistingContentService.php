<?php

namespace WPML\Core\Component\Translation\Application\Service;

use WPML\Core\Component\Translation\Application\Repository\SettingsRepository;

class TranslateExistingContentService {

  private $settingsRepository;


  public function __construct( SettingsRepository $settingsRepository ) {
    $this->settingsRepository = $settingsRepository;
  }


  public function handle( array $postTypes, array $packageTypes ) {
    $this->settingsRepository->removeTranslateEverythingCompleted( $postTypes, $packageTypes );
  }


}
