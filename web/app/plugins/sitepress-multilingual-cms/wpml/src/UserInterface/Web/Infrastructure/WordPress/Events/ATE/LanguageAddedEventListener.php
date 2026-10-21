<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Events\ATE;

use WPML\Core\Component\Translation\Application\Repository\SettingsRepository;
use WPML\Core\SharedKernel\Component\ATE\Application\Service\AtePingerInterface;
use WPML\Core\SharedKernel\Component\ATE\Application\Service\TeaLoggerInterface;
use WPML\Core\SharedKernel\Component\ATE\Application\Service\TranslateEverythingStateInterface;
use WPML\Core\SharedKernel\Component\Language\Application\Query\LanguagesQueryInterface;

class LanguageAddedEventListener {

  private $settingsRepository;

  private $atePinger;

  private $logger;

  private $languagesQuery;

  private $translateEverythingState;


  public function __construct(
    SettingsRepository $settingsRepository,
    AtePingerInterface $atePinger,
    TeaLoggerInterface $logger,
    LanguagesQueryInterface $languagesQuery,
    TranslateEverythingStateInterface $translateEverythingState
  ) {
    $this->settingsRepository       = $settingsRepository;
    $this->atePinger                = $atePinger;
    $this->logger                   = $logger;
    $this->languagesQuery           = $languagesQuery;
    $this->translateEverythingState = $translateEverythingState;
  }


  public static function isSetupComplete() {
    return function_exists( 'wpml_is_setup_complete' ) && wpml_is_setup_complete();
  }


  public function doActions( $oldActiveLanguages = null ) {
    $teaEnabled = $this->settingsRepository->getSettings()->getTranslateEverything()->isEnabled();

    if ( ! $teaEnabled ) {
      return;
    }

    $this->logger->beginLanguageAdded();

    try {
      if ( ! is_array( $oldActiveLanguages ) ) {
        $this->logger->languageAddedSkipped( 'no_old_state' );
        return;
      }

      $currentCodes = array_map(
        fn( $dto ) => $dto->getCode(),
        $this->languagesQuery->getActive()
      );

      $addedCodes = array_diff( $currentCodes, array_keys( $oldActiveLanguages ) );

      if ( empty( $addedCodes ) ) {
        $this->logger->languageAddedSkipped( 'no_addition' );
        return;
      }

      if ( $this->translateEverythingState->isEverythingProcessed() ) {
        $this->logger->languageAddedSkipped( 'nothing_to_translate' );
        return;
      }

      $this->atePinger->notifyTeaEnabled( AtePingerInterface::TRIGGER_LANGUAGE_ADDED );
    } finally {
      $this->logger->end();
    }
  }


}
