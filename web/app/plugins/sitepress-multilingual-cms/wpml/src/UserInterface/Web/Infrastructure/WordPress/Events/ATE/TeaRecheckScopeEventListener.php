<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Events\ATE;

use WPML\Core\Component\Translation\Application\Repository\SettingsRepository;
use WPML\Core\SharedKernel\Component\ATE\Application\Service\AtePingerInterface;
use WPML\Core\SharedKernel\Component\ATE\Application\Service\TeaLoggerInterface;
use WPML\Core\SharedKernel\Component\ATE\Application\Service\TranslateEverythingStateInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Repository\ParkedTypesOfferRepositoryInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Service\ParkedTypesService;

class TeaRecheckScopeEventListener {

  private $settingsRepository;

  private $atePinger;

  private $logger;

  private $translateEverythingState;

  private $parkedOffer;

  private $parkedTypesService;


  public function __construct(
    SettingsRepository $settingsRepository,
    AtePingerInterface $atePinger,
    TeaLoggerInterface $logger,
    TranslateEverythingStateInterface $translateEverythingState,
    ParkedTypesOfferRepositoryInterface $parkedOffer,
    ParkedTypesService $parkedTypesService
  ) {
    $this->settingsRepository       = $settingsRepository;
    $this->atePinger                = $atePinger;
    $this->logger                   = $logger;
    $this->translateEverythingState = $translateEverythingState;
    $this->parkedOffer              = $parkedOffer;
    $this->parkedTypesService       = $parkedTypesService;
  }


  public function doActions( string $trigger = AtePingerInterface::TRIGGER_TRANSLATABLE_SCOPE_RECHECK ) {
    $teaEnabled = $this->settingsRepository->getSettings()->getTranslateEverything()->isEnabled();

    if ( ! $teaEnabled ) {
      return;
    }

    $this->logger->beginTeaRecheckScope();

    try {
      $this->parkedTypesService->parkTypesTheSinceMapNeverGot( $trigger );

      if ( $this->translateEverythingState->isEverythingProcessed() ) {
        $parkedTypes = array_keys( $this->parkedOffer->getParkedTypes() );

        if ( $parkedTypes !== [] ) {
          $this->logger->teaRecheckScopeParked( $parkedTypes );
          return;
        }

        $this->logger->teaRecheckScopeSkipped( 'nothing_to_translate' );
        return;
      }

      $this->atePinger->notifyTeaEnabled( $trigger );
    } finally {
      $this->logger->end();
    }
  }


}
