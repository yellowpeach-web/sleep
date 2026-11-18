<?php

namespace WPML\Core\Component\PostHog\Application\Service\Event;

use WPML\Core\Component\PostHog\Application\Repository\PostHogStateRepositoryInterface;
use WPML\Core\Component\PostHog\Domain\Repository\SetupWizardUUIDRepositoryInterface;
use WPML\Core\Component\PostHog\Domain\TrackingMode;

class ClientEventWizardPropertiesService {

  const UI_LOCATION_SETUP_WIZARD = 'setup_wizard';

  private $wizardUUIDRepository;

  private $postHogStateRepository;


  public function __construct(
    SetupWizardUUIDRepositoryInterface $wizardUUIDRepository,
    PostHogStateRepositoryInterface $postHogStateRepository
  ) {
    $this->wizardUUIDRepository   = $wizardUUIDRepository;
    $this->postHogStateRepository = $postHogStateRepository;
  }


  public function forUiLocation( ?string $uiLocation ): array {
    $properties = [];

    if (
      self::UI_LOCATION_SETUP_WIZARD === $uiLocation
      && TrackingMode::ALL === $this->postHogStateRepository->getTrackingMode()
    ) {
      $wizardUUID = $this->wizardUUIDRepository->get();
      if ( is_string( $wizardUUID ) && '' !== $wizardUUID ) {
        $properties['wizard_uuid'] = $wizardUUID;
      }
    }

    return $properties;
  }


}
