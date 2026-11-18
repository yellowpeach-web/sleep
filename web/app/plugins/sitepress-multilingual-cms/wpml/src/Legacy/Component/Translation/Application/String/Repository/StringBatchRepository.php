<?php

namespace WPML\Legacy\Component\Translation\Application\String\Repository;

use WPML\Core\Component\Translation\Application\String\Repository\StringBatchRepositoryInterface;
use WPML\Core\Component\Translation\Application\String\StringException;
use WPML\Core\Port\Persistence\DatabaseWriteInterface;

use function WPML\PHP\Logger\error;

class StringBatchRepository implements StringBatchRepositoryInterface {

  private $databaseWrite;

  private $sitepress;


  public function __construct(
    DatabaseWriteInterface $databaseWrite,
    \SitePress $sitepress
  ) {
    $this->databaseWrite = $databaseWrite;
    $this->sitepress     = $sitepress;
  }


  public function create( string $name, array $stringIds, string $sourceLanguageCode ): int {
    $stringIds = $this->withoutCarriedStrings( $stringIds );

    if ( ! $stringIds ) {
      throw new StringException( 'Every string of the batch is translated as part of a String Package; no batch is created.' );
    }

    try {
      $batchId = $this->databaseWrite->insert(
        'icl_translation_batches',
        [
          'batch_name'  => $name,
          'last_update' => date( 'Y-m-d H:i:s' ),
        ]
      );

      $rowset = array_map(
        function ( $stringId ) use ( $batchId ) {
          return [
            'string_id' => $stringId,
            'batch_id'  => $batchId,
          ];
        },
        $stringIds
      );
      $this->databaseWrite->insertMany( 'icl_string_batches', $rowset );

      $this->sitepress->set_element_language_details(
        $batchId,
        'st-batch_strings',
        null,
        $sourceLanguageCode
      );

      return $batchId;
    } catch ( \Throwable $e ) {
      throw new StringException( $e->getMessage(), (int) $e->getCode(), $e );
    }
  }


  private function withoutCarriedStrings( array $stringIds ): array {
    $carried = function_exists( 'apply_filters' ) ? \apply_filters( 'wpml_st_carried_string_ids', [] ) : [];
    $carried = is_array( $carried ) ? array_map( 'intval', $carried ) : [];

    if ( ! $carried ) {
      return $stringIds;
    }

    $kept = array_values(
      array_filter(
        $stringIds,
        function ( $stringId ) use ( $carried ) {
          return ! in_array( $stringId, $carried, true );
        }
      )
    );

    if ( count( $kept ) !== count( $stringIds ) ) {
      error( 'String batch: strings translated as part of a String Package were left out: ' . implode( ', ', array_diff( array_map( 'intval', $stringIds ), $kept ) ) );
    }

    return $kept;
  }


  public function removeStrings( int $batchId, array $stringIds ): int {
    $removed = 0;

    try {
      foreach ( array_unique( $stringIds ) as $stringId ) {
        $removed += $this->databaseWrite->delete(
          'icl_string_batches',
          [
            'batch_id'  => $batchId,
            'string_id' => $stringId,
          ]
        );
      }
    } catch ( \Throwable $e ) {
      throw new StringException( 'The strings could not be removed from the string batch.' );
    }

    return $removed;
  }


  public function deleteUnsent( int $batchId ) {
    try {
      $trid = $this->sitepress->get_element_trid( $batchId, 'st-batch_strings' );
      if ( $trid ) {
        $this->sitepress->delete_element_translation( (string) $trid, 'st-batch_strings' );
      }

      $this->databaseWrite->delete( 'icl_translation_batches', [ 'id' => $batchId ] );
      $this->databaseWrite->delete( 'icl_string_batches', [ 'batch_id' => $batchId ] );
    } catch ( \Throwable $e ) {
      throw new StringException( 'The string batch could not be deleted.' );
    }
  }


}
