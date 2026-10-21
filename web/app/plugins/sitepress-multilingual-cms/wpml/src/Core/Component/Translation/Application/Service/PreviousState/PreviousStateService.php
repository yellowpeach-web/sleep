<?php

namespace WPML\Core\Component\Translation\Application\Service\PreviousState;

use WPML\Core\Component\Translation\Domain\PreviousState\PreviousState;
use WPML\Core\Component\Translation\Domain\PreviousState\PreviousStateQueryInterface;
use WPML\Core\Component\Translation\Domain\PreviousState\PreviousStateRepositoryInterface;
use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;
use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus;
use WPML\PHP\Exception\InvalidItemIdException;


class PreviousStateService {

  const REVERT_CHUNK = 1000;

  private $previousStateQuery;

  private $previousStateRepository;


  public function __construct(
    PreviousStateQueryInterface $previousStateQuery,
    PreviousStateRepositoryInterface $previousStateRepository
  ) {
    $this->previousStateQuery      = $previousStateQuery;
    $this->previousStateRepository = $previousStateRepository;
  }


  public function revertToPreviousState(
    int $translationId,
    int $untranslatedStatus = TranslationStatus::NOT_TRANSLATED
  ): int {
    $previousState    = $this->previousStateQuery->getByTranslationId( $translationId );
    $holdsTranslation = $previousState && in_array(
      $previousState->getStatus()->get(),
      [ TranslationStatus::COMPLETE, TranslationStatus::DUPLICATE ],
      true
    );

    if ( $holdsTranslation ) {
      $this->previousStateRepository->restoreState( $translationId, $previousState );
      $status = $previousState->getStatus()->get();
    } else {
      $status = $this->previousStateQuery->hasTranslatedElement( $translationId )
        ? TranslationStatus::COMPLETE
        : $untranslatedStatus;
      $this->previousStateRepository->resetStatus( $translationId, $status );
    }

    $this->previousStateRepository->update( $translationId, null );

    return $status;
  }


  public function revertInFlight(
    array $rids,
    array $inFlightStatuses,
    int $untranslatedStatus = TranslationStatus::NOT_TRANSLATED
  ): array {
    if ( ! $rids ) {
      return [];
    }

    $restored = [];
    foreach ( $this->previousStateQuery->getTranslationIdsByRids( $rids, $inFlightStatuses ) as $translationId ) {
      try {
        $restored[ $translationId ] = $this->revertToPreviousState( $translationId, $untranslatedStatus );
      } catch ( InvalidItemIdException $e ) {
        continue;
      }
    }

    return $restored;
  }


  public function revertInFlightWithoutSnapshot(
    array $rids,
    array $inFlightStatuses,
    int $untranslatedStatus = TranslationStatus::NOT_TRANSLATED
  ): int {
    $reverted = 0;
    foreach ( array_chunk( $rids, self::REVERT_CHUNK ) as $chunk ) {
      $reverted += $this->previousStateRepository->resetStatusesWithoutPreviousState(
        $chunk,
        $inFlightStatuses,
        TranslationStatus::COMPLETE,
        $untranslatedStatus
      );
    }

    return $reverted;
  }


  public function get( int $translationId ) {
    $previousState = $this->previousStateQuery->getByTranslationId( $translationId );

    if ( ! $previousState ) {
      return null;
    }

    return [
      'status'              => $previousState->getStatus()->get(),
      'translator_id'       => $previousState->getTranslatorId(),
      'needs_update'        => $previousState->getNeedsUpdate(),
      'md5'                 => $previousState->getMd5(),
      'translation_service' => $previousState->getTranslationService(),
      'timestamp'           => $previousState->getTimestamp(),
      'links_fixed'         => $previousState->getLinksFixed()
    ];
  }


  public function getByRid( int $rid ) {
    $previousState = $this->previousStateQuery->getByRID( $rid );

    if ( ! $previousState ) {
      return null;
    }

    return [
      'status'              => $previousState->getStatus()->get(),
      'translator_id'       => $previousState->getTranslatorId(),
      'needs_update'        => $previousState->getNeedsUpdate(),
      'md5'                 => $previousState->getMd5(),
      'translation_service' => $previousState->getTranslationService(),
      'timestamp'           => $previousState->getTimestamp(),
      'links_fixed'         => $previousState->getLinksFixed()
    ];
  }


  public function update( int $translationId, array $data ): bool {
    try {
      if (
        ! isset( $data['status'] ) ||
        ! in_array( (int) $data['status'], TranslationStatus::getAll(), true )
      ) {
        return false;
      }

      $mergedData = $this->mergeWithDefaultData( $data );
      $status     = new TranslationStatus( $mergedData['status'] );

      $previousState = new PreviousState(
        $status,
        $mergedData['translator_id'],
        $mergedData['needs_update'],
        $mergedData['md5'],
        $mergedData['translation_service'],
        $mergedData['timestamp'],
        $mergedData['links_fixed']
      );

      $this->previousStateRepository->update( $translationId, $previousState );

      return true;
    } catch ( InvalidItemIdException $e ) {
      return false;
    }
  }


  private function mergeWithDefaultData( array $data ): array {
    $defaultData = [
      'translator_id'       => 0,
      'needs_update'        => false,
      'md5'                 => '',
      'translation_service' => 'local',
      'timestamp'           => '0',
      'links_fixed'         => false
    ];

    $data           = array_merge( $defaultData, $data );
    $data['status'] = (int) $data['status'];

    return $data;
  }


}
