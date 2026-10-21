<?php

namespace WPML\Infrastructure\WordPress\Component\Translation\Domain\PreviousState;

use WPML\Core\Component\Translation\Domain\PreviousState\DataCompressInterface;
use WPML\Core\Component\Translation\Domain\PreviousState\PreviousState;
use WPML\Core\Component\Translation\Domain\PreviousState\PreviousStateRepositoryInterface;
use WPML\Core\Port\Persistence\DatabaseWriteInterface;
use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;
use WPML\Core\Port\Persistence\QueryPrepareInterface;
use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus;
use WPML\PHP\Exception\InvalidItemIdException;

class PreviousStateRepository implements PreviousStateRepositoryInterface {

  private $dbWriter;

  private $dataCompress;

  private $queryPrepare;


  public function __construct(
    DatabaseWriteInterface $dbWriter,
    DataCompressInterface $dataCompress,
    QueryPrepareInterface $queryPrepare
  ) {
    $this->dbWriter     = $dbWriter;
    $this->dataCompress = $dataCompress;
    $this->queryPrepare = $queryPrepare;
  }


  public function update( int $translationId, ?PreviousState $previousState = null ) {
    $data = $previousState ? $this->dataCompress->compress( $previousState->toArray() ) : null;

    try {
      $updatedItems = $this->dbWriter->update(
        'icl_translation_status',
        [ '_prevstate' => $data ],
        [ 'translation_id' => $translationId ]
      );

      if ( $data !== null && $updatedItems === 0 ) {
        throw $this->getException( $translationId );
      }
    } catch ( DatabaseErrorException $e ) {
      throw $this->getException( $translationId );
    }
  }


  public function resetStatus( int $translationId, int $status = TranslationStatus::NOT_TRANSLATED ) {
    try {
      $updatedItems = $this->dbWriter->update(
        'icl_translation_status',
        [ 'status' => $status ],
        [ 'translation_id' => $translationId ]
      );

      if ( $updatedItems === 0 ) {
        throw $this->getException( $translationId );
      }
    } catch ( DatabaseErrorException $e ) {
      throw $this->getException( $translationId );
    }
  }


  public function restoreState( int $translationId, PreviousState $previousState ) {
    try {
      $data = $previousState->toArray();

      $updatedItems = $this->dbWriter->update(
        'icl_translation_status',
        $data,
        [ 'translation_id' => $translationId ]
      );

      if ( $updatedItems === 0 ) {
        throw $this->getException( $translationId );
      }
    } catch ( DatabaseErrorException $e ) {
      throw $this->getException( $translationId );
    }
  }


  public function resetStatusesWithoutPreviousState(
    array $rids,
    array $fromStatuses,
    int $statusWithElement,
    int $statusWithoutElement
  ): int {
    if ( ! $rids || ! $fromStatuses ) {
      return 0;
    }

    $prepare = $this->queryPrepare;
    $sql     = $prepare->prepare(
      'UPDATE ' . $prepare->prefix() . 'icl_translation_status s
       LEFT JOIN ' . $prepare->prefix() . 'icl_translations t ON t.translation_id = s.translation_id
       SET s.status = IF( t.element_id IS NULL, %d, %d )
       WHERE s.rid IN (' . $prepare->prepareIn( array_map( 'intval', $rids ), '%d' ) . ')
         AND s.status IN (' . $prepare->prepareIn( array_map( 'intval', $fromStatuses ), '%d' ) . ')
         AND s._prevstate IS NULL',
      $statusWithoutElement,
      $statusWithElement
    );

    return $this->dbWriter->execute( $sql );
  }


  private function getException( int $translationId ): InvalidItemIdException {
    return new InvalidItemIdException(
      sprintf( 'Translation with ID: %d could not be updated.', $translationId )
    );
  }


}
