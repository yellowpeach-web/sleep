<?php

namespace WPML\Infrastructure\WordPress\Component\Translation\Application\String\Query;

use WPML\Core\Component\Translation\Application\String\Query\UnsentStringBatchesQueryInterface;
use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;
use WPML\Core\Port\Persistence\QueryHandlerInterface;
use WPML\Core\Port\Persistence\QueryPrepareInterface;

class UnsentStringBatchesQuery implements UnsentStringBatchesQueryInterface {

  private $queryHandler;

  private $queryPrepare;


  public function __construct(
    QueryHandlerInterface $queryHandler,
    QueryPrepareInterface $queryPrepare
  ) {
    $this->queryHandler = $queryHandler;
    $this->queryPrepare = $queryPrepare;
  }


  public function get( array $batchIds ): array {
    $batchIds = array_values( array_unique( array_filter( array_map( 'intval', $batchIds ) ) ) );
    if ( ! $batchIds ) {
      return [];
    }

    $prefix = $this->queryPrepare->prefix();
    $ids    = $this->queryPrepare->prepareIn( $batchIds, '%d' );

    $query = "
      SELECT `own`.`element_id`
      FROM `{$prefix}icl_translations` AS `own`
      WHERE `own`.`element_type` = 'st-batch_strings'
        AND `own`.`element_id` IN ($ids)
        AND NOT EXISTS (
          SELECT 1
          FROM `{$prefix}icl_translations` AS `other`
          WHERE `other`.`trid` = `own`.`trid`
            AND `other`.`translation_id` <> `own`.`translation_id`
        )";

    $unsent = [];
    foreach ( $this->queryHandler->query( $query )->getResults() as $row ) {
      $unsent[] = (int) ( is_numeric( $row['element_id'] ?? null ) ? $row['element_id'] : 0 );
    }

    return array_values( array_unique( array_filter( $unsent ) ) );
  }


}
