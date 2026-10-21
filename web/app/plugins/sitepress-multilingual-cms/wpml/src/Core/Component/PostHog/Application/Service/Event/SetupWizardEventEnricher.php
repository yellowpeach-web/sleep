<?php

namespace WPML\Core\Component\PostHog\Application\Service\Event;

use WPML\Core\Component\PostHog\Domain\Event\EventInterface;
use WPML\Core\Component\PostHog\Domain\Event\SetupWizard\WizardStarted\Event as WizardStartedEvent;
use WPML\Core\Component\PostHog\Domain\Event\SetupWizard\SetupWizardUUIDInterface;
use WPML\Core\Component\PostHog\Domain\Event\SetupWizard\WizardCompleted\Event as WizardCompletedEvent;
use WPML\Core\Component\PostHog\Domain\Event\SetupWizard\WizardFirstStepCompleted\Event as WizardFirstStepCompletedEvent;
use WPML\Core\Component\PostHog\Domain\Event\SetupWizard\WizardStepCompleted\Event as WizardStepCompletedEvent;
use WPML\Core\Component\PostHog\Domain\Repository\SetupWizardLastStepSubmissionTimeRepositoryInterface;
use WPML\Core\Component\PostHog\Domain\Repository\SetupWizardStartTimeRepositoryInterface;
use WPML\Core\Component\PostHog\Domain\Repository\SetupWizardUUIDRepositoryInterface;

class SetupWizardEventEnricher {

  private $wizardUUID;

  private $wizardUUIDRepository;

  private $wizardStartTimeRepository;

  private $wizardLastStepSubmissionTimeRepository;


  public function __construct(
    SetupWizardUUIDInterface $wizardUUID,
    SetupWizardUUIDRepositoryInterface $wizardUUIDRepository,
    SetupWizardStartTimeRepositoryInterface $wizardStartTimeRepository,
    SetupWizardLastStepSubmissionTimeRepositoryInterface $wizardLastStepSubmissionTimeRepository
  ) {
    $this->wizardUUID                           = $wizardUUID;
    $this->wizardUUIDRepository                 = $wizardUUIDRepository;
    $this->wizardStartTimeRepository            = $wizardStartTimeRepository;
    $this->wizardLastStepSubmissionTimeRepository = $wizardLastStepSubmissionTimeRepository;
  }


  public function enrich( EventInterface $event ): bool {
    if ( $event instanceof WizardStartedEvent ) {
      return $this->enrichStarted( $event );
    }

    if ( $event instanceof WizardFirstStepCompletedEvent ) {
      return $this->enrichFirstStepCompleted( $event );
    }

    if ( $event instanceof WizardStepCompletedEvent ) {
      return $this->enrichStepCompleted( $event );
    }

    if ( $event instanceof WizardCompletedEvent ) {
      return $this->enrichCompleted( $event );
    }

    return false;
  }


  private function enrichStarted( WizardStartedEvent $event ): bool {
    $wizardUUID = $this->wizardUUID->create();
    $startTime  = time();
    $this->wizardStartTimeRepository->save( $wizardUUID, $startTime );

    $event->addProperties(
      [
        'wizard_uuid'       => $wizardUUID,
        'wizard_start_time' => $startTime,
      ]
    );

    return true;
  }


  private function enrichFirstStepCompleted( WizardFirstStepCompletedEvent $event ): bool {
    $wizardUUID = $this->wizardUUIDRepository->get();
    if ( ! $wizardUUID ) {
      return false;
    }

    $stepSubmissionTime = time();
    $wizardStartTime    = $this->wizardStartTimeRepository->get( $wizardUUID );
    $stepDuration       = $wizardStartTime ? $stepSubmissionTime - $wizardStartTime : null;

    $this->wizardLastStepSubmissionTimeRepository->save( $stepSubmissionTime );

    $event->addProperties(
      [
        'wizard_uuid'           => $wizardUUID,
        'step_duration_seconds' => $stepDuration,
      ]
    );

    return true;
  }


  private function enrichStepCompleted( WizardStepCompletedEvent $event ): bool {
    $wizardUUID = $this->wizardUUIDRepository->get();
    if ( ! $wizardUUID ) {
      return false;
    }

    $stepSubmissionTime      = time();
    $lastStepSubmissionTime  = $this->wizardLastStepSubmissionTimeRepository->get();
    $stepDuration            = $lastStepSubmissionTime ? $stepSubmissionTime - $lastStepSubmissionTime : null;

    $this->wizardLastStepSubmissionTimeRepository->save( $stepSubmissionTime );

    $event->addProperties(
      [
        'wizard_uuid'           => $wizardUUID,
        'step_duration_seconds' => $stepDuration,
      ]
    );

    return true;
  }


  private function enrichCompleted( WizardCompletedEvent $event ): bool {
    $wizardUUID        = $this->wizardUUIDRepository->get();
    $now               = time();
    $wizardStartTime   = $wizardUUID ? $this->wizardStartTimeRepository->get( $wizardUUID ) : false;
    $wizardDuration    = $wizardStartTime ? $now - $wizardStartTime : 0;

    $event->addProperties(
      [
        'wizard_uuid'             => $wizardUUID,
        'wizard_duration_seconds' => $wizardDuration,
      ]
    );

    return true;
  }

}
