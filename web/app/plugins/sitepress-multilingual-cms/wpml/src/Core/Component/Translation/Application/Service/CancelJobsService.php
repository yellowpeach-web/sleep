<?php

namespace WPML\Core\Component\Translation\Application\Service;

use WPML\Core\Component\Translation\Application\Event\JobsCancelledEvent;
use WPML\Core\Component\Translation\Application\Query\TranslationQueryInterface;
use WPML\Core\Component\Translation\Application\Repository\TranslationRepositoryInterface;
use WPML\Core\Component\Translation\Application\Service\PreviousState\PreviousStateService;
use WPML\Core\Component\Translation\Domain\Translation;
use WPML\Core\Component\Translation\Domain\TranslationEditor\AteEditor;
use WPML\Core\Component\Translation\Domain\TranslationMethod\AutomaticMethod;
use WPML\Core\Component\Translation\Domain\TranslationType;
use WPML\Core\Port\Event\DispatcherInterface;
use WPML\Core\SharedKernel\Component\String\Application\Service\StringBatchCleanupService;
use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus;

class CancelJobsService {

  private $translationQuery;

  private $translationRepository;

  private $previousStateService;

  private $eventDispatcher;

  private $stringBatchCleanupService;


  public function __construct(
    TranslationQueryInterface $translationQuery,
    TranslationRepositoryInterface $translationRepository,
    PreviousStateService $previousStateService,
    DispatcherInterface $eventDispatcher,
    StringBatchCleanupService $stringBatchCleanupService
  ) {
    $this->translationQuery            = $translationQuery;
    $this->translationRepository       = $translationRepository;
    $this->previousStateService        = $previousStateService;
    $this->eventDispatcher             = $eventDispatcher;
    $this->stringBatchCleanupService   = $stringBatchCleanupService;
  }


  public function cancelJobs( array $jobIds, bool $keepOutOfAutomaticTranslation = false ): array {
    if ( empty( $jobIds ) ) {
      return [
        'cancelledJobIds'  => [],
        'restoredStatuses' => [],
      ];
    }

    $cancelledJobIds  = [];
    $restoredStatuses = [];
    $jobDataForEvent  = [];

    foreach ( $jobIds as $jobId ) {
      $translation = $this->translationQuery->getOneByJobId( $jobId );
      if ( ! $translation ) {
        if ( $this->cancelOrphanJob( $jobId ) ) {
          $cancelledJobIds[]          = $jobId;
          $restoredStatuses[ $jobId ] = TranslationStatus::NOT_TRANSLATED;
        }
        continue;
      }

      $translationType = $translation->getType()->get();

      $previousState = $this->previousStateService->get( $translation->getId() );

      if ( $previousState === null && $translation->getTranslatedElementId() === null ) {
        $this->translationRepository->setCancelledStatus( $translation->getId() );
        $restoredStatuses[ $jobId ] = TranslationStatus::NOT_TRANSLATED;
      } else {
        $restoredStatus             = $this->previousStateService->revertToPreviousState( $translation->getId() );
        $restoredStatuses[ $jobId ] = TranslationStatus::COMPLETE === $restoredStatus
                                      && TranslationStatus::COMPLETE === ( $previousState['status'] ?? null )
                                      && ! empty( $previousState['needs_update'] )
          ? TranslationStatus::NEEDS_UPDATE
          : $restoredStatus;
      }

      $keepOut = $keepOutOfAutomaticTranslation && $this->isAutomatic( $translation );

      if ( $translationType === TranslationType::STRING_BATCH ) {
        $this->stringBatchCleanupService->cleanupBatch(
          $translation->getOriginalElementId(),
          $translation->getTargetLanguageCode(),
          $keepOut
        );
      }

      $cancelledJobIds[] = $jobId;
      $jobDataForEvent[] = $this->buildJobDataForEvent( $translation, $keepOut );
    }

    if ( ! empty( $jobDataForEvent ) ) {
      $this->eventDispatcher->dispatch(
        new JobsCancelledEvent( $jobDataForEvent )
      );
    }

    return [
      'cancelledJobIds'  => $cancelledJobIds,
      'restoredStatuses' => $restoredStatuses,
    ];
  }


  public function cancelJobsInBatch( int $batchId ): array {
    $jobIds = $this->translationQuery->getJobIdsByBatchId( $batchId );

    if ( empty( $jobIds ) ) {
      return [
        'cancelledJobIds'  => [],
        'restoredStatuses' => [],
      ];
    }

    return $this->cancelJobs( $jobIds );
  }


  private function cancelOrphanJob( int $jobId ): bool {
    $translationId = $this->translationQuery->getTranslationIdByJobId( $jobId );
    if ( $translationId === null ) {
      return false;
    }

    $this->translationRepository->setCancelledStatus( $translationId );

    return true;
  }


  private function isAutomatic( Translation $translation ): bool {
    $job = $translation->getJob();

    return $job !== null && $job->getTranslationMethod() instanceof AutomaticMethod;
  }


  private function buildJobDataForEvent( Translation $translation, bool $keepErrorRecord = false ) {
    $job = $translation->getJob();

    $jobData = (object) [];
    $jobData->keep_error_record = $keepErrorRecord;
    $jobData->job_id        = $job ? $job->getId() : null;
    $jobData->editor        = null;
    $jobData->editor_job_id = null;
    $jobData->translator_id      = null;
    $jobData->translation_method = null;

    if ( $job ) {
      $editor                      = $job->getEditor();
      $jobData->editor             = $editor->get();
      $jobData->translator_id      = $job->getTranslatorId();
      $jobData->translation_method = $job->getTranslationMethod()->get();

      if ( $editor instanceof AteEditor ) {
        $jobData->editor_job_id = $editor->getEditorJobId();
      }
    }

    return $jobData;
  }


}
