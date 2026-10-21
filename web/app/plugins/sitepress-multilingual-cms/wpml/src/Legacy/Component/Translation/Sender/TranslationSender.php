<?php

namespace WPML\Legacy\Component\Translation\Sender;

use WPML\Core\Component\Translation\Application\Query\TranslationQueryInterface;
use WPML\Core\Component\Translation\Application\Service\Dto\SendToTranslationExtraInformationDto;
use WPML\Core\Component\Translation\Application\String\Query\UnsentStringBatchesQueryInterface;
use WPML\Core\Component\Translation\Application\String\Repository\StringBatchRepositoryInterface;
use WPML\Core\Component\Translation\Application\String\StringBatchToStringsTranslationsMapper;
use WPML\Core\Component\Translation\Application\String\StringException;
use WPML\Core\Component\Translation\Domain\Sender\SendBatchException;
use WPML\Core\Component\Translation\Domain\Sender\TranslationSenderInterface;
use WPML\Core\Component\Translation\Domain\Translation;
use WPML\Core\Component\Translation\Domain\TranslationBatch\TargetLanguage;
use WPML\Core\Component\Translation\Domain\TranslationBatch\TranslationBatch;
use WPML\Core\Component\Translation\Domain\TranslationType;
use WPML\Core\Component\Translation\Domain\TranslationMethod\TranslationServiceMethod;
use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;
use WPML\Legacy\Component\Translation\Sender\ErrorMapper\ErrorMapper;
use function WPML\PHP\Logger\error;

class TranslationSender implements TranslationSenderInterface {

  const SEND_VIA_DASHBOARD = 6;

  private $legacyTranslationManagement;

  private $translationBatchMapper;

  private $translationQuery;

  private $errorMapper;

  private $guard;

  private $unsentStringBatches;

  private $stringBatches;


  private $ignoredElements = [];

  private $taxonomyTermBatchSender = null;

  private $taxonomyFailedElements = [];

  private $reportedStringIds = [];


  public function __construct(
    TranslationBatchMapper $translationBatchMapper,
    TranslationQueryInterface $translationQuery,
    ErrorMapper $errorMapper,
    AteResendGuardInterface $guard,
    UnsentStringBatchesQueryInterface $unsentStringBatches,
    StringBatchRepositoryInterface $stringBatches
  ) {
    $this->legacyTranslationManagement = \wpml_load_core_tm();
    $this->translationBatchMapper      = $translationBatchMapper;
    $this->translationQuery            = $translationQuery;
    $this->errorMapper                 = $errorMapper;
    $this->guard                       = $guard;
    $this->unsentStringBatches         = $unsentStringBatches;
    $this->stringBatches               = $stringBatches;
  }


  public function getFailedElements(): array {
    $failed = $this->taxonomyFailedElements;
    if ( method_exists( $this->legacyTranslationManagement, 'get_failed_send_elements' ) ) {
      $failed = array_merge( $failed, (array) $this->legacyTranslationManagement->get_failed_send_elements() );
    }

    return $failed;
  }


  public function getHeldBackTranslations(): array {
    return $this->translationsOfJobs( $this->heldBackJobIds() );
  }


  public function wasTruncated(): bool {
    if ( method_exists( $this->legacyTranslationManagement, 'send_was_truncated' ) ) {
      return (bool) $this->legacyTranslationManagement->send_was_truncated();
    }

    return false;
  }


  public function send( TranslationBatch $batch, bool $mayTruncate = false, bool $isRetry = false, bool $sendAnyway = false ): array {
    return $this->guard->duringUserSend(
      function () use ( $batch, $mayTruncate, $isRetry, $sendAnyway ) {
        return $this->sendGuarded( $batch, $mayTruncate, $isRetry, $sendAnyway );
      }
    );
  }


  private function sendGuarded( TranslationBatch $batch, bool $mayTruncate, bool $isRetry, bool $sendAnyway = false ): array {
    $this->taxonomyFailedElements = [];
    $this->reportedStringIds      = [];

    $prepared  = $this->guard->prepare( $batch, $isRetry, $sendAnyway );
    $abortText = $prepared->getAbortText();
    if ( null !== $abortText ) {
      $this->throwSendBatchException( $abortText, [], $prepared->getSendAnywayElements() );
    }

    $reportedJobIds          = $prepared->getReportedJobIds();
    $this->reportedStringIds = $prepared->getReportedStringIds();
    $hadElements             = $this->hasAnyElement( $batch );
    $batch                   = $prepared->getBatch();

    if ( $hadElements && ! $this->hasAnyElement( $batch ) ) {
      return $this->translationsOfJobs( $reportedJobIds );
    }

    $termTranslations = [];

    if ( $this->hasTaxonomyElements( $batch ) ) {
      list( $termTranslations, $this->ignoredElements, $this->taxonomyFailedElements ) = $this->taxonomyTermBatchSender()->send( $batch );

      $batch = $this->withoutTaxonomyElements( $batch );

      if ( ! $this->hasAnyElement( $batch ) ) {
        return array_merge( $termTranslations, $this->translationsOfJobs( $reportedJobIds ) );
      }
    }

    $this->setTargetLanguagesInTranslationProxy( $batch );

    $translationProxyBatchInfo = null;

    $batchHasJobsForTranslationProxy = $this->getTargetLanguagesForTranslationProxy( $batch );

    if ( $batchHasJobsForTranslationProxy ) {
      $translationProxyBatchInfo = [
        'batchName'   => $batch->getBatchName(),
        'deadline'    => $batch->getDeadline(),
        'extraFields' => $batch->getTranslationServiceExtraFields()
      ];
    }

    $legacyBatches = $this->translationBatchMapper->map( $batch, $translationProxyBatchInfo );

    $jobIds = [];

    if ( $mayTruncate
         && $batchHasJobsForTranslationProxy
         && method_exists( $this->legacyTranslationManagement, 'start_send_budget' ) ) {
      $this->legacyTranslationManagement->start_send_budget();
    }

    foreach ( $legacyBatches as $legacyBatch ) {
      foreach ( $this->getElementTypes() as $type ) {
        do_action(
          'wpml_tm_send_' . $type . '_jobs',
          $legacyBatch,
          $type,
          self::SEND_VIA_DASHBOARD
        );
      }

      $jobIdsOfLegacy = $this->legacyTranslationManagement->get_sent_job_ids();
      if ( is_array( $jobIdsOfLegacy ) ) {
        $jobIds = array_merge( $jobIds, $jobIdsOfLegacy );
      }

      $errors = $this->legacyTranslationManagement->messages_by_type( 'error' );
      if ( is_array( $errors ) ) {
        $errorMessage = $this->errorMapper->map( $errors );
        $translations = $this->translationsOfJobs(
          array_merge( array_diff( $jobIds, $this->failedCreateJobIds(), $this->heldBackJobIds() ), $reportedJobIds )
        );

        $this->deleteUnsentStringBatches( $batch );

        $this->throwSendBatchException( $errorMessage, $translations );
      }

      do_action( 'wpml_tm_jobs_notification' );
    }

    $this->deleteUnsentStringBatches( $batch );

    $sentJobIds   = array_merge( array_diff( $jobIds, $this->heldBackJobIds() ), $reportedJobIds );
    $translations = $this->translationsOfJobs( $sentJobIds );
    $this->throwIfJobsWentMissing( $sentJobIds, $translations );

    return array_merge( $termTranslations, $translations );
  }


  private function throwIfJobsWentMissing( array $sentJobIds, array $translations ) {
    $sent = array_values( array_unique( array_filter( array_map( 'intval', $sentJobIds ) ) ) );
    if ( ! $sent || $translations ) {
      return;
    }

    $message = method_exists( $this->legacyTranslationManagement, 'describe_unconfirmed_send' )
      ? (string) $this->legacyTranslationManagement->describe_unconfirmed_send( count( $sent ) )
      : sprintf(
        'WPML sent %d job(s) but could not read them back from the database. Error code: send_not_confirmed.',
        count( $sent )
      );

    $this->throwSendBatchException( $message, $translations );
  }


  private function throwSendBatchException( string $message, array $translations, array $sendAnywayElements = [] ) {
    throw new SendBatchException( $message, $translations, 0, $sendAnywayElements );
  }


  private function translationsOfJobs( array $jobIds ): array {
    $jobIds = array_values( array_unique( array_map( 'intval', $jobIds ) ) );

    return $jobIds ? $this->withReportedStringsOnly( $this->translationQuery->getManyByJobIds( $jobIds ) ) : [];
  }


  private function withReportedStringsOnly( array $translations ): array {
    if ( ! $this->reportedStringIds ) {
      return $translations;
    }

    $listed = [];
    foreach ( $translations as $translation ) {
      $job = $translation->getJob();
      if (
        $job
        && TranslationType::STRING_BATCH === $translation->getType()->get()
        && isset( $this->reportedStringIds[ $job->getId() ] )
      ) {
        $listed = array_merge(
          $listed,
          StringBatchToStringsTranslationsMapper::forStrings( $translation, $this->reportedStringIds[ $job->getId() ] )
        );
        continue;
      }

      $listed[] = $translation;
    }

    return $listed;
  }


  private function deleteUnsentStringBatches( TranslationBatch $batch ) {
    $batchIds = [];
    foreach ( $batch->getTargetLanguages() as $targetLanguage ) {
      foreach ( $targetLanguage->getElements() as $element ) {
        if ( TranslationType::STRING_BATCH === $element->getType()->get() ) {
          $batchIds[] = $element->getElementId();
        }
      }
    }

    if ( ! $batchIds ) {
      return;
    }

    try {
      $unsent = $this->unsentStringBatches->get( $batchIds );
    } catch ( DatabaseErrorException $e ) {
      error( 'wpmldev-10973: the string batches a send left with no job could not be read; they stay.' );

      return;
    }

    foreach ( $unsent as $batchId ) {
      try {
        $this->stringBatches->deleteUnsent( $batchId );
      } catch ( StringException $e ) {
        error( 'wpmldev-10973: the unsent string batch ' . $batchId . ' could not be deleted.' );
      }
    }
  }


  private function heldBackJobIds(): array {
    if ( ! method_exists( $this->legacyTranslationManagement, 'get_held_back_job_ids' ) ) {
      return [];
    }

    $jobIds = $this->legacyTranslationManagement->get_held_back_job_ids();

    return is_array( $jobIds ) ? array_map( 'intval', $jobIds ) : [];
  }


  private function failedCreateJobIds(): array {
    if ( ! method_exists( $this->legacyTranslationManagement, 'get_failed_create_job_ids' ) ) {
      return [];
    }

    $jobIds = $this->legacyTranslationManagement->get_failed_create_job_ids();

    return is_array( $jobIds ) ? array_map( 'intval', $jobIds ) : [];
  }


  public function getIgnoredElements(): array {
    return $this->ignoredElements;
  }


  private function taxonomyTermBatchSender(): TaxonomyTermBatchSender {
    if ( null === $this->taxonomyTermBatchSender ) {
      $this->taxonomyTermBatchSender = new TaxonomyTermBatchSender(
        $GLOBALS['wpdb'],
        $this->translationQuery
      );
    }

    return $this->taxonomyTermBatchSender;
  }


  private function getTargetLanguagesForTranslationProxy( TranslationBatch $batch ): array {
    $targetLanguages = [];

    foreach ( $batch->getTargetLanguages() as $targetLanguage ) {
      if ( $targetLanguage->getMethod() instanceof TranslationServiceMethod ) {
        $targetLanguages[] = $targetLanguage->getLanguageCode();
      }
    }

    return array_unique( $targetLanguages );
  }


  private function setTargetLanguagesInTranslationProxy( TranslationBatch $batch ) {
    $targetLanguages = $this->getTargetLanguagesForTranslationProxy( $batch );
    if ( $targetLanguages ) {
      \WPML\TM\TranslationProxy\TpBatchState::setRemoteTargetLanguages( $targetLanguages );
    }

  }


  private function getElementTypes(): array {
    $types = \apply_filters(
      'wpml_tm_basket_items_types',
      [
        'st-batch' => 'core',
        'post'     => 'core',
        'package'  => 'custom',
      ]
    );

    return array_keys( $types );
  }


  private function withoutTaxonomyElements( TranslationBatch $batch ): TranslationBatch {
    $targetLanguages = [];

    foreach ( $batch->getTargetLanguages() as $targetLanguage ) {
      $elements = array_values(
        array_filter(
          $targetLanguage->getElements(),
          function ( $element ) {
            return $element->getType()->get() !== TranslationType::TAXONOMY;
          }
        )
      );

      if ( $elements ) {
        $targetLanguages[] = new TargetLanguage(
          $targetLanguage->getLanguageCode(),
          $targetLanguage->getMethod(),
          $elements
        );
      }
    }

    return $batch->copyWithNewTargetLanguages( $targetLanguages );
  }


  private function hasTaxonomyElements( TranslationBatch $batch ): bool {
    foreach ( $batch->getTargetLanguages() as $targetLanguage ) {
      foreach ( $targetLanguage->getElements() as $element ) {
        if ( $element->getType()->get() === TranslationType::TAXONOMY ) {
          return true;
        }
      }
    }

    return false;
  }


  private function hasAnyElement( TranslationBatch $batch ): bool {
    foreach ( $batch->getTargetLanguages() as $targetLanguage ) {
      if ( $targetLanguage->getElements() ) {
        return true;
      }
    }

    return false;
  }


  public function cleanupAfterFailedSend( TranslationBatch $batch ) {
    if ( ! $this->getTargetLanguagesForTranslationProxy( $batch ) ) {
      return;
    }

    \WPML\TM\TranslationProxy\TpBatchState::setBatchData( null );
    icl_cache_clear_preserving_language_names();
  }


}
