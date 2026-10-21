<?php

namespace WPML\Infrastructure\WordPress\Component\Translation\Application\Query;

use WPML\Core\Component\Translation\Application\Query\NewestJobsForPairsQueryInterface;
use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;
use WPML\Core\Port\Persistence\QueryHandlerInterface;
use WPML\Core\Port\Persistence\QueryPrepareInterface;

class NewestJobsForPairsQuery implements NewestJobsForPairsQueryInterface {

  private $queryHandler;

  private $queryPrepare;


  public function __construct(
    QueryHandlerInterface $queryHandler,
    QueryPrepareInterface $queryPrepare
  ) {
    $this->queryHandler = $queryHandler;
    $this->queryPrepare = $queryPrepare;
  }


  public function getForElements( string $elementTypePrefix, array $elementIds, array $languageCodes ): array {
    if ( ! $elementIds || ! $languageCodes ) {
      return [];
    }

    $prefix = $this->queryPrepare->prefix();
    $ids    = $this->queryPrepare->prepareIn( $elementIds, '%d' );
    $langs  = $this->queryPrepare->prepareIn( $languageCodes, '%s' );
    $type = $this->queryPrepare->prepare( 'AND `original`.`element_type` LIKE %s', $elementTypePrefix . '\_%' );

    $query = "
      SELECT
        {$this->jobColumns()},
        `original`.`element_id` AS `element_id`,
        `translation`.`language_code`
      FROM `{$prefix}icl_translations` AS `translation`
      INNER JOIN `{$prefix}icl_translations` AS `original`
        ON `original`.`trid` = `translation`.`trid`
        AND `original`.`source_language_code` IS NULL
        AND `translation`.`source_language_code` IS NOT NULL
      {$this->joinNewestJob()}
      WHERE `original`.`element_id` IN ($ids)
        {$type}
        AND `translation`.`language_code` IN ($langs)
      ORDER BY `job`.`job_id` DESC";

    $newest = [];
    foreach ( $this->rows( $query ) as $row ) {
      $pair = $row['elementId'] . '|' . $row['languageCode'];
      if ( ! isset( $newest[ $pair ] ) ) {
        $newest[ $pair ] = $row;
      }
    }

    return array_values( $newest );
  }


  public function getForStringsOfBatch( int $batchId, string $languageCode ): array {
    $stringIds = $this->getStringIdsOfBatch( $batchId );
    if ( ! $stringIds ) {
      return [];
    }

    $batchesOfString = $this->otherBatchesOfStrings( $batchId, $stringIds );
    if ( ! $batchesOfString ) {
      return [];
    }

    $otherBatchIds = array_values( array_unique( array_merge( ...array_values( $batchesOfString ) ) ) );
    $jobOfBatch    = $this->newestJobOfBatches( $otherBatchIds, $languageCode );

    $newest = [];
    foreach ( $batchesOfString as $stringId => $otherBatches ) {
      foreach ( $otherBatches as $otherBatchId ) {
        if ( ! isset( $jobOfBatch[ $otherBatchId ] ) ) {
          continue;
        }

        $row = $jobOfBatch[ $otherBatchId ];
        if ( ! isset( $newest[ $stringId ] ) || $row['jobId'] > $newest[ $stringId ]['jobId'] ) {
          $row['stringId']     = $stringId;
          $newest[ $stringId ] = $row;
        }
      }
    }

    ksort( $newest );

    return $newest;
  }


  public function getStringIdsOfBatch( int $batchId ): array {
    $query = $this->queryPrepare->prepare(
      "SELECT `string_id` FROM `{$this->queryPrepare->prefix()}icl_string_batches` WHERE `batch_id` = %d",
      $batchId
    );

    $rows = $this->queryHandler->query( $query )->getResults();

    $stringIds = [];
    foreach ( $rows as $row ) {
      $stringIds[] = (int) ( is_numeric( $row['string_id'] ?? null ) ? $row['string_id'] : 0 );
    }

    return array_values( array_unique( array_filter( $stringIds ) ) );
  }


  private function otherBatchesOfStrings( int $batchId, array $stringIds ): array {
    $prefix = $this->queryPrepare->prefix();
    $ids    = $this->queryPrepare->prepareIn( $stringIds, '%d' );
    $notOwn = $this->queryPrepare->prepare( 'AND `batch_id` <> %d', $batchId );

    $query = "
      SELECT `batch_id`, `string_id`
      FROM `{$prefix}icl_string_batches`
      WHERE `string_id` IN ($ids)
        {$notOwn}";

    $rows = $this->queryHandler->query( $query )->getResults();

    $batchesOfString = [];
    foreach ( $rows as $row ) {
      $stringId     = $this->int( $row, 'string_id' );
      $otherBatchId = $this->int( $row, 'batch_id' );
      if ( $stringId > 0 && $otherBatchId > 0 ) {
        $batchesOfString[ $stringId ][] = $otherBatchId;
      }
    }

    return $batchesOfString;
  }


  private function newestJobOfBatches( array $batchIds, string $languageCode ): array {
    $prefix = $this->queryPrepare->prefix();
    $ids    = $this->queryPrepare->prepareIn( $batchIds, '%d' );
    $lang   = $this->queryPrepare->prepare( 'AND `translation`.`language_code` = %s', $languageCode );

    $query = "
      SELECT
        {$this->jobColumns()},
        `original`.`element_id` AS `element_id`,
        `translation`.`language_code`
      FROM `{$prefix}icl_translations` AS `original`
      INNER JOIN `{$prefix}icl_translations` AS `translation`
        ON `translation`.`trid` = `original`.`trid`
        AND `translation`.`source_language_code` IS NOT NULL
        {$lang}
      {$this->joinNewestJob()}
      WHERE `original`.`element_id` IN ($ids)
        AND `original`.`element_type` = 'st-batch_strings'
        AND `original`.`source_language_code` IS NULL
      ORDER BY `job`.`job_id` DESC";

    $newest = [];
    foreach ( $this->rows( $query ) as $row ) {
      if ( ! isset( $newest[ $row['elementId'] ] ) ) {
        $newest[ $row['elementId'] ] = $row;
      }
    }

    return $newest;
  }


  private function jobColumns(): string {
    return "
        `job`.`job_id`,
        `status`.`rid`,
        `status`.`status`,
        `job`.`editor`,
        `job`.`editor_job_id`,
        `status`.`translation_service`,
        `status`.`md5`,
        `status`.`needs_update`";
  }


  private function joinNewestJob(): string {
    $prefix = $this->queryPrepare->prefix();

    return "
      INNER JOIN `{$prefix}icl_translation_status` AS `status`
        ON `status`.`translation_id` = `translation`.`translation_id`
      INNER JOIN `{$prefix}icl_translate_job` AS `job`
        ON `job`.`rid` = `status`.`rid`
        AND `job`.`revision` IS NULL";
  }


  private function rows( string $query ): array {
    $rows = $this->queryHandler->query( $query )->getResults();

    $mapped = [];
    foreach ( $rows as $row ) {
      $mapped[] = [
        'jobId'              => $this->int( $row, 'job_id' ),
        'rid'                => $this->int( $row, 'rid' ),
        'status'             => $this->int( $row, 'status' ),
        'editor'             => isset( $row['editor'] ) && is_scalar( $row['editor'] ) ? (string) $row['editor'] : null,
        'editorJobId'        => isset( $row['editor_job_id'] ) ? $this->int( $row, 'editor_job_id' ) : null,
        'translationService' => isset( $row['translation_service'] ) && is_scalar( $row['translation_service'] ) ? (string) $row['translation_service'] : '',
        'md5'                => isset( $row['md5'] ) && is_scalar( $row['md5'] ) ? (string) $row['md5'] : '',
        'needsUpdate'        => $this->int( $row, 'needs_update' ),
        'elementId'          => $this->int( $row, 'element_id' ),
        'languageCode'       => isset( $row['language_code'] ) && is_scalar( $row['language_code'] ) ? (string) $row['language_code'] : '',
        'stringId'           => $this->int( $row, 'string_id' ),
      ];
    }

    return $mapped;
  }


  private function int( array $row, string $column ): int {
    return isset( $row[ $column ] ) && is_numeric( $row[ $column ] ) ? (int) $row[ $column ] : 0;
  }


}
