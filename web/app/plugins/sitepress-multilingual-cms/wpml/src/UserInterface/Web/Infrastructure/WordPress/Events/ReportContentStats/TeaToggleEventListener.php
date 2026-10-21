<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Events\ReportContentStats;

use WPML\Core\Component\ReportContentStats\Application\Service\EventReasonService;
use WPML\Core\Component\ReportContentStats\Application\Service\LastSentService;
use WPML\Core\Component\ReportContentStats\Application\Service\ResendTriggerService;
use WPML\Core\Component\ReportContentStats\Application\Service\TeaChangeDetectionService;

class TeaToggleEventListener {

  private $resendTriggerService;

  private $lastSentService;

  private $teaChangeDetectionService;


  public function __construct(
    ResendTriggerService $resendTriggerService,
    LastSentService $lastSentService,
    TeaChangeDetectionService $teaChangeDetectionService
  ) {
    $this->resendTriggerService      = $resendTriggerService;
    $this->lastSentService           = $lastSentService;
    $this->teaChangeDetectionService = $teaChangeDetectionService;
  }


  public function doActions( bool $enabled ) {
    if ( $this->lastSentService->get() === null ) {
      return;
    }

    if ( ! $this->teaChangeDetectionService->differsFromReported( $enabled ) ) {
      return;
    }

    $this->resendTriggerService->trigger( EventReasonService::REASON_TEA_TOGGLED );
  }


}
