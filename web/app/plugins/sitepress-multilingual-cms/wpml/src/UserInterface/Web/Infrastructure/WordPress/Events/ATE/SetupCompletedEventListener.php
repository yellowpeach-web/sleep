<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Events\ATE;

use WPML\Core\Component\Translation\Application\Repository\SettingsRepository;
use WPML\Core\SharedKernel\Component\ATE\Application\Service\AtePingerInterface;
use WPML\Core\SharedKernel\Component\ATE\Application\Service\TeaLoggerInterface;

class SetupCompletedEventListener {

  private $settingsRepository;

  private $atePinger;

  private $logger;


  public function __construct(
    SettingsRepository $settingsRepository,
    AtePingerInterface $atePinger,
    TeaLoggerInterface $logger
  ) {
    $this->settingsRepository = $settingsRepository;
    $this->atePinger          = $atePinger;
    $this->logger             = $logger;
  }


  public function doActions() {
    $settings   = $this->settingsRepository->getSettings();
    $teaEnabled = $settings->getTranslateEverything()->isEnabled();
    $tmAllowed  = $settings->isTMAllowed();

    $this->logger->beginWizardCompletion();

    try {
      $this->logger->setupCompletedListenerFired( $teaEnabled, $tmAllowed );

      if ( ! $teaEnabled ) {
        $this->logger->setupCompletedNotifySkipped();
        return;
      }

      $this->atePinger->notifyTeaEnabled( AtePingerInterface::TRIGGER_WIZARD_COMPLETION );
    } finally {
      $this->logger->end();
    }
  }


}
