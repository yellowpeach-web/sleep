<?php

namespace WPML\Legacy\Component\Translation\Sender;

use WPML\Core\Component\Translation\Application\Query\NewestJobsForPairsQueryInterface;
use WPML\Core\Component\Translation\Application\String\Repository\StringBatchRepositoryInterface;
use WPML\Core\Component\Translation\Application\String\StringException;
use WPML\Core\Component\Translation\Domain\TranslationBatch\TargetLanguage;
use WPML\Core\Component\Translation\Domain\TranslationBatch\TranslationBatch;
use WPML\Core\Component\Translation\Domain\TranslationMethod\AutomaticMethod;
use WPML\Core\Component\Translation\Domain\TranslationMethod\LocalTranslatorMethod;
use WPML\Core\Component\Translation\Domain\TranslationType;
use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;
use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationEditorType;
use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus;
use function WPML\PHP\Logger\error;

class AteResendGuard implements AteResendGuardInterface {

  const UNCONFIRMED_OPTION_PREFIX = 'wpml_tm_ate_create_unconfirmed_';

  const IN_FLIGHT_OPTION_PREFIX = 'wpml_tm_ate_create_in_flight_';

  const ATE_RECORD_LIVE_STATUSES = [
    TranslationStatus::WAITING_FOR_TRANSLATOR,
    TranslationStatus::IN_PROGRESS,
    TranslationStatus::COMPLETE,
    TranslationStatus::NEEDS_REVIEW,
  ];

  private $reconciler;

  private $newestJobs;

  private $stringBatches;


  public function __construct(
    \WPML\TM\ATE\JobCreation\AteCreateReconciler $reconciler,
    NewestJobsForPairsQueryInterface $newestJobs,
    StringBatchRepositoryInterface $stringBatches
  ) {
    $this->reconciler    = $reconciler;
    $this->newestJobs    = $newestJobs;
    $this->stringBatches = $stringBatches;
  }


  public static function create(
    NewestJobsForPairsQueryInterface $newestJobs,
    StringBatchRepositoryInterface $stringBatches
  ): self {
    return new self(
      new \WPML\TM\ATE\JobCreation\AteCreateReconciler(
        \WPML\Container\make( \WPML_TM_ATE_API::class ),
        \WPML\Container\make( \WPML_TM_ATE_Jobs::class )
      ),
      $newestJobs,
      $stringBatches
    );
  }


  public function duringUserSend( callable $call ) {
    return \WPML\TM\ATE\ClonedSites\ApiCommunication::duringUserSend( $call );
  }


  public function prepare( TranslationBatch $batch, bool $isRetry, bool $sendAnyway = false ): AteResendGuardResult {
    if ( ! \WPML_TM_ATE_Status::is_enabled() ) {
      return new AteResendGuardResult( $batch );
    }

    $stringsOfBatch = [];
    try {
      $pairs = $this->pairsSentToAte( $batch, $stringsOfBatch );
    } catch ( DatabaseErrorException $e ) {
      return $this->abort( $batch, null );
    }
    if ( ! $pairs ) {
      return new AteResendGuardResult( $batch );
    }

    $removed        = [];
    $reported       = [];
    $confirmedMarks = [];
    $candidates     = [];
    $modeByJobId    = [];

    foreach ( $pairs as $key => $pair ) {
      $jobId = $pair['row']['jobId'];

      if ( $this->isInFlight( $jobId ) ) {
        $removed[ $key ] = true;
        $reported[]      = $jobId;
        continue;
      }

      if ( $isRetry && $this->turnedOutToExist( $pair['row'] ) ) {
        $removed[ $key ]  = true;
        $reported[]       = $jobId;
        $confirmedMarks[] = $jobId;
        continue;
      }

      if ( $this->isCandidate( $pair ) ) {
        if ( $sendAnyway && \WPML\TM\ATE\JobCreation\AteCreateReconciler::sendAnywayDue( $jobId ) ) {
          continue;
        }

        $candidates[ $jobId ][] = $key;
        $modeByJobId[ $jobId ]  = $this->bindModeOf( $pair );
      }
    }

    $adopted = [];
    if ( $candidates ) {
      $result = $this->reconciler->reconcile( array_keys( $candidates ) );

      if ( 'no_answer' === $result['outcome'] ) {
        return $this->abort(
          $batch,
          $result['error'],
          empty( $result['unreadable'] ) ? [] : $this->sendAnywayElements( $batch, $pairs, $candidates )
        );
      }

      if ( 'adopted' === $result['outcome'] ) {
        $adopted = $result['adopted'];

        foreach ( array_keys( $adopted ) as $jobId ) {
          foreach ( $candidates[ $jobId ] ?? [] as $key ) {
            $removed[ $key ] = true;
          }
          $reported[] = $jobId;
        }
      }
    }

    $removedStrings = $this->stringsOfRemovedPairs( $pairs, $removed );
    if ( ! $this->removeStrings( $removedStrings ) ) {
      return $this->abort( $batch, null );
    }

    if ( $adopted ) {
      $this->reconciler->bind(
        $adopted,
        array_intersect_key( $modeByJobId, $adopted ),
        TranslationSender::SEND_VIA_DASHBOARD
      );
    }

    foreach ( $confirmedMarks as $jobId ) {
      if ( 'auto' === \WPML\TM\ATE\JobCreation\AteCreateReconciler::markedMode( $jobId )
           && \WPML\TM\ATE\JobCreation\AteCreateReconciler::markedByClone( $jobId ) ) {
        continue;
      }
      delete_option( self::UNCONFIRMED_OPTION_PREFIX . $jobId );
    }

    if ( $confirmedMarks || $adopted ) {
      \WPML\TM\ATE\ClonedSites\ApiCommunication::noteCreateConfirmed();
    }

    $emptied = $this->emptiedBatchIds( $removedStrings, $stringsOfBatch );
    $this->deleteUnsentBatches( $emptied );

    return new AteResendGuardResult(
      $this->withoutPairs( $batch, $removed, $emptied ),
      $reported,
      null,
      $this->stringsOfReportedJobs( $pairs, $removed )
    );
  }


  private function abort( TranslationBatch $batch, $error, array $sendAnywayElements = [] ): AteResendGuardResult {
    $this->deleteUnsentBatches( $this->stringBatchIdsOf( $batch ) );

    return new AteResendGuardResult( $batch, [], $this->abortText( $error ), [], $sendAnywayElements );
  }


  private function sendAnywayElements( TranslationBatch $batch, array $pairs, array $candidates ): array {
    $languages = [];
    foreach ( $batch->getTargetLanguages() as $languageIndex => $targetLanguage ) {
      $languages[ $languageIndex ] = $targetLanguage->getLanguageCode();
    }

    $elements = [];
    foreach ( $candidates as $jobId => $keys ) {
      if ( ! \WPML\TM\ATE\JobCreation\AteCreateReconciler::sendAnywayDue( $jobId ) ) {
        continue;
      }

      foreach ( $keys as $key ) {
        $pair = $pairs[ $key ];
        if ( TranslationType::STRING_BATCH === $pair['type'] ) {
          $parts     = explode( '|', $key );
          $elements[] = [
            'elementType'    => TranslationType::STRING,
            'elementId'      => (int) end( $parts ),
            'targetLanguage' => $languages[ $pair['languageIndex'] ] ?? '',
          ];
          continue;
        }

        $elements[] = [
          'elementType'    => $pair['type'],
          'elementId'      => $pair['elementId'],
          'targetLanguage' => $languages[ $pair['languageIndex'] ] ?? '',
        ];
      }
    }

    return $elements;
  }


  private function pairsSentToAte( TranslationBatch $batch, array &$stringsOfBatch ): array {
    $pairs = [];

    foreach ( $batch->getTargetLanguages() as $languageIndex => $targetLanguage ) {
      $method = $targetLanguage->getMethod();
      if ( ! $method instanceof AutomaticMethod && ! $method instanceof LocalTranslatorMethod ) {
        continue;
      }

      $mode     = $method instanceof AutomaticMethod ? 'auto' : 'manual';
      $language = $targetLanguage->getLanguageCode();
      $byType   = [];
      foreach ( $targetLanguage->getElements() as $element ) {
        $byType[ $element->getType()->get() ][] = $element->getElementId();
      }

      foreach ( [ TranslationType::POST, TranslationType::PACKAGE ] as $type ) {
        if ( empty( $byType[ $type ] ) ) {
          continue;
        }

        $rows = $this->newestJobs->getForElements(
          $this->elementTypePrefixOf( $type ),
          $byType[ $type ],
          [ $language ]
        );
        foreach ( $rows as $row ) {
          if ( $row['languageCode'] !== $language || ! in_array( $row['elementId'], $byType[ $type ], true ) ) {
            continue;
          }

          $pairs[ $languageIndex . '|' . $type . '|' . $row['elementId'] ] = [
            'languageIndex' => (int) $languageIndex,
            'type'          => $type,
            'elementId'     => $row['elementId'],
            'mode'          => $mode,
            'row'           => $row,
          ];
        }
      }

      foreach ( $byType[ TranslationType::STRING_BATCH ] ?? [] as $batchId ) {
        $stringsOfBatch[ $batchId ] = $this->newestJobs->getStringIdsOfBatch( $batchId );

        foreach ( $this->newestJobs->getForStringsOfBatch( $batchId, $language ) as $stringId => $row ) {
          $pairs[ $languageIndex . '|' . TranslationType::STRING_BATCH . '|' . $batchId . '|' . $stringId ] = [
            'languageIndex' => (int) $languageIndex,
            'type'          => TranslationType::STRING_BATCH,
            'elementId'     => $batchId,
            'mode'          => $mode,
            'row'           => $row,
          ];
        }
      }
    }

    return $pairs;
  }


  private function isCandidate( array $pair ): bool {
    $row = $pair['row'];

    if ( 'local' !== $row['translationService'] || (int) $row['editorJobId'] > 0 ) {
      return false;
    }

    $waiting = TranslationStatus::WAITING_FOR_TRANSLATOR === $row['status'];
    $leftWaiting = $waiting && ( null === $row['editor'] || TranslationEditorType::NONE === $row['editor'] );
    $leftByTea = $waiting && TranslationEditorType::ATE === $row['editor'];
    $leftParked = TranslationStatus::ATE_NEEDS_RETRY === $row['status']
                  && TranslationEditorType::ATE === $row['editor'];
    $marked = $this->hasUnconfirmedMark( $row['jobId'] );
    if ( ! $leftWaiting && ! $leftByTea && ! $leftParked && ! $marked ) {
      return false;
    }

    if (
      'auto' === $pair['mode']
      && 'manual' === \WPML\TM\ATE\JobCreation\AteCreateReconciler::markedMode( $row['jobId'] )
    ) {
      return false;
    }

    if ( wpml_tm_load_old_jobs_editor()->shouldStickToWPMLEditor( $row['jobId'] ) ) {
      return false;
    }

    return $this->isSourceUnchanged( $pair );
  }


  private function isSourceUnchanged( array $pair ): bool {
    $row = $pair['row'];

    if ( 0 !== $row['needsUpdate'] ) {
      return false;
    }

    $translationManagement = \wpml_load_core_tm();
    $item                  = $translationManagement->get_post( $row['elementId'], $this->elementTypePrefixOf( $pair['type'] ) );

    return $item && ( new \WPML_TM_Action_Helper() )->post_md5_matches( $item, $row['md5'] );
  }


  private function bindModeOf( array $pair ): string {
    if ( \WPML\TM\ATE\JobCreation\AteCreateReconciler::markedByClone( $pair['row']['jobId'] ) ) {
      return 'manual';
    }

    $marked = \WPML\TM\ATE\JobCreation\AteCreateReconciler::markedMode( $pair['row']['jobId'] );
    if ( null !== $marked ) {
      return $marked;
    }

    return TranslationEditorType::ATE === $pair['row']['editor'] ? 'auto' : $pair['mode'];
  }


  private function turnedOutToExist( array $row ): bool {
    return (int) $row['editorJobId'] > 0
           && in_array( $row['status'], self::ATE_RECORD_LIVE_STATUSES, true )
           && $this->hasUnconfirmedMark( $row['jobId'] );
  }


  private function elementTypePrefixOf( string $type ): string {
    switch ( $type ) {
      case TranslationType::PACKAGE:
        return TranslationType::package()->getElementTypePrefix();
      case TranslationType::STRING_BATCH:
        return TranslationType::stringBatch()->getElementTypePrefix();
      default:
        return TranslationType::post()->getElementTypePrefix();
    }
  }


  private function isInFlight( int $jobId ): bool {
    $expiry = get_option( self::IN_FLIGHT_OPTION_PREFIX . $jobId, 0 );

    return is_numeric( $expiry ) && (int) $expiry > time();
  }


  private function hasUnconfirmedMark( int $jobId ): bool {
    return false !== get_option( self::UNCONFIRMED_OPTION_PREFIX . $jobId, false );
  }


  private function abortText( $error ): string {
    return \WPML\TM\ATE\JobCreation\CreateFailureText::from(
      $error instanceof \WP_Error ? $error : \WPML\TM\ATE\JobCreation\CreateFailureText::noAnswerError()
    );
  }


  private function stringsOfRemovedPairs( array $pairs, array $removed ): array {
    $removedStrings = [];
    foreach ( array_keys( $removed ) as $key ) {
      $pair = $pairs[ $key ];
      if ( TranslationType::STRING_BATCH === $pair['type'] ) {
        $removedStrings[ $pair['elementId'] ][] = $pair['row']['stringId'];
      }
    }

    return $removedStrings;
  }


  private function removeStrings( array $removedStrings ): bool {
    try {
      foreach ( $removedStrings as $batchId => $stringIds ) {
        $this->stringBatches->removeStrings( $batchId, $stringIds );
      }
    } catch ( StringException $e ) {
      return false;
    }

    return true;
  }


  private function emptiedBatchIds( array $removedStrings, array $stringsOfBatch ): array {
    $emptied = [];
    foreach ( $removedStrings as $batchId => $stringIds ) {
      $ownStrings = $stringsOfBatch[ $batchId ] ?? [];
      if ( $ownStrings && ! array_diff( $ownStrings, $stringIds ) ) {
        $emptied[] = $batchId;
      }
    }

    return $emptied;
  }


  private function deleteUnsentBatches( array $batchIds ) {
    foreach ( $batchIds as $batchId ) {
      try {
        $this->stringBatches->deleteUnsent( $batchId );
      } catch ( StringException $e ) {
        error( 'wpmldev-10183: the unsent string batch ' . $batchId . ' could not be deleted.' );
      }
    }
  }


  private function stringBatchIdsOf( TranslationBatch $batch ): array {
    $batchIds = [];
    foreach ( $batch->getTargetLanguages() as $targetLanguage ) {
      foreach ( $targetLanguage->getElements() as $element ) {
        if ( TranslationType::STRING_BATCH === $element->getType()->get() ) {
          $batchIds[] = $element->getElementId();
        }
      }
    }

    return array_values( array_unique( $batchIds ) );
  }


  private function stringsOfReportedJobs( array $pairs, array $removed ): array {
    $stringsOfJob = [];
    foreach ( array_keys( $removed ) as $key ) {
      $pair = $pairs[ $key ];
      if ( TranslationType::STRING_BATCH === $pair['type'] ) {
        $stringsOfJob[ $pair['row']['jobId'] ][] = $pair['row']['stringId'];
      }
    }

    return $stringsOfJob;
  }


  private function withoutPairs(
    TranslationBatch $batch,
    array $removed,
    array $emptied
  ): TranslationBatch {
    if ( ! $removed ) {
      return $batch;
    }

    $targetLanguages = [];
    foreach ( $batch->getTargetLanguages() as $languageIndex => $targetLanguage ) {
      $elements = [];
      foreach ( $targetLanguage->getElements() as $element ) {
        $type      = $element->getType()->get();
        $elementId = $element->getElementId();

        if ( isset( $removed[ $languageIndex . '|' . $type . '|' . $elementId ] ) ) {
          continue;
        }

        if ( TranslationType::STRING_BATCH === $type && in_array( $elementId, $emptied, true ) ) {
          continue;
        }

        $elements[] = $element;
      }

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


}
