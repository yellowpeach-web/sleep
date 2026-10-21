<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Events\ATE;

use WPML\Core\Component\Translation\Application\Repository\SettingsRepository;
use WPML\Core\SharedKernel\Component\ATE\Application\Service\AtePingerInterface;
use WPML\Core\SharedKernel\Component\ATE\Application\Service\TeaLoggerInterface;
use WPML\Core\SharedKernel\Component\ATE\Application\Service\TranslateEverythingStateInterface;

class PostTypeBecameTranslatableEventListener {

  private $settingsRepository;

  private $atePinger;

  private $logger;

  private $translateEverythingState;


  public function __construct(
    SettingsRepository $settingsRepository,
    AtePingerInterface $atePinger,
    TeaLoggerInterface $logger,
    TranslateEverythingStateInterface $translateEverythingState
  ) {
    $this->settingsRepository       = $settingsRepository;
    $this->atePinger                = $atePinger;
    $this->logger                   = $logger;
    $this->translateEverythingState = $translateEverythingState;
  }


  public function doActions() {
    $teaEnabled = $this->settingsRepository->getSettings()->getTranslateEverything()->isEnabled();

    if ( ! $teaEnabled ) {
      return;
    }

    $this->logger->beginPostTypeBecameTranslatable();

    try {
      if ( $this->translateEverythingState->isEverythingProcessed() ) {
        $this->logger->postTypeBecameTranslatableSkipped( 'nothing_to_translate' );
        return;
      }

      $this->atePinger->notifyTeaEnabled( AtePingerInterface::TRIGGER_POST_TYPE_BECAME_TRANSLATABLE );
    } finally {
      $this->logger->end();
    }
  }


}
