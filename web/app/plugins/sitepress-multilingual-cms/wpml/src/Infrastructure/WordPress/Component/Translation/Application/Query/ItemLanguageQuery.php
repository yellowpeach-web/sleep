<?php

namespace WPML\Infrastructure\WordPress\Component\Translation\Application\Query;

use WPML\Core\Component\Translation\Application\Query\ItemLanguageQueryInterface;
use WPML\Core\Component\Translation\Domain\TranslationType;
use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;
use WPML\Core\Port\Persistence\QueryHandlerInterface;
use WPML\Core\Port\Persistence\QueryPrepareInterface;


class ItemLanguageQuery implements ItemLanguageQueryInterface {

  private $queryHandler;

  private $queryPrepare;


  public function __construct(
    QueryHandlerInterface $queryHandler,
    QueryPrepareInterface $queryPrepare
  ) {
    $this->queryHandler = $queryHandler;
    $this->queryPrepare = $queryPrepare;
  }


  public function getManyOriginalLanguagesOfItems( array $items ): array {
    if ( ! $items ) {
      return [];
    }

    $where = implode(
      ' OR ',
      array_map(
        function ( $item ) {
          $type = $item['type']->getElementTypePrefix();

          return $this->queryPrepare->prepare(
            "( element_id = %d AND element_type LIKE %s )",
            $item['itemId'],
            $type . '_%'
          );
        },
        $items
      )
    );

    $sql = "
      SELECT
        element_id as itemId,
        element_type as type,
        (
          SELECT language_code
          FROM {$this->queryPrepare->prefix()}icl_translations original_element
          WHERE original_element.trid = current_element.trid 
            AND original_element.source_language_code IS NULL           
        ) as language
      FROM {$this->queryPrepare->prefix()}icl_translations current_element      
      WHERE {$where}
    ";

    try {
      $rowset = $this->queryHandler->query( $sql )->getResults();
    } catch ( DatabaseErrorException $e ) {
      $rowset = [];
    }

    return array_map(
      function ( $row ) {
        return [
          'itemId'   => $row['itemId'],
          'type'     => TranslationType::fromElementType( $row['type'] ),
          'language' => $row['language'],
        ];
      },
      $rowset
    );
  }


}
