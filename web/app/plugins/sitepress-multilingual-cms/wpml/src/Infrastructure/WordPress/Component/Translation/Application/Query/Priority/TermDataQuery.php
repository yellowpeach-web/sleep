<?php

namespace WPML\Infrastructure\WordPress\Component\Translation\Application\Query\Priority;

use WPML\Core\Component\Translation\Application\Query\Priority\TermDataQueryInterface;
use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;
use WPML\Core\Port\Persistence\QueryHandlerInterface;
use WPML\Core\Port\Persistence\QueryPrepareInterface;

class TermDataQuery implements TermDataQueryInterface {

    const MAX_ANCESTOR_LEVELS = 32;

    private $queryHandler;

    private $queryPrepare;


  public function __construct(
        QueryHandlerInterface $queryHandler,
        QueryPrepareInterface $queryPrepare
    ) {
      $this->queryHandler = $queryHandler;
      $this->queryPrepare = $queryPrepare;
  }


  public function getDepthMap( array $termTaxonomyIds ): array {
      $ids = array_values( array_unique( array_map( 'intval', $termTaxonomyIds ) ) );

    if ( empty( $ids ) ) {
        return [];
    }

      $rows = $this->fetch( 'term_taxonomy_id', $ids );

    if ( empty( $rows ) ) {
        return [];
    }

      $parentOf = [];
    foreach ( $rows as $row ) {
        $parentOf[ $this->key( $row['taxonomy'], $row['termId'] ) ] = $row['parent'];
    }

      $pending = $this->unresolvedAncestors( $rows, $parentOf );

    for ( $level = 0; $level < self::MAX_ANCESTOR_LEVELS && ! empty( $pending ); $level++ ) {
        $ancestors = $this->fetch( 'term_id', $pending );

      if ( empty( $ancestors ) ) {
        break;
      }

        $added = [];
      foreach ( $ancestors as $row ) {
          $key = $this->key( $row['taxonomy'], $row['termId'] );
        if ( array_key_exists( $key, $parentOf ) ) {
            continue;
        }
          $parentOf[ $key ] = $row['parent'];
          $added[]          = $row;
      }

        $pending = $this->unresolvedAncestors( $added, $parentOf );
    }

      $depths = [];
    foreach ( $rows as $row ) {
        $depths[ $row['termTaxonomyId'] ] = $this->walk( $row['taxonomy'], $row['termId'], $parentOf );
    }

      return $depths;
  }


  private function unresolvedAncestors( array $rows, array $parentOf ): array {
      $unresolved = [];

    foreach ( $rows as $row ) {
      if ( $row['parent'] > 0 && ! array_key_exists( $this->key( $row['taxonomy'], $row['parent'] ), $parentOf ) ) {
          $unresolved[ $row['parent'] ] = true;
      }
    }

      return array_keys( $unresolved );
  }


  private function walk( string $taxonomy, int $termId, array $parentOf ): int {
      $depth   = 0;
      $current = $termId;
      $visited = [];

    while ( $depth < self::MAX_ANCESTOR_LEVELS && ! isset( $visited[ $current ] ) ) {
        $visited[ $current ] = true;
        $key                 = $this->key( $taxonomy, $current );

      if ( ! isset( $parentOf[ $key ] ) || $parentOf[ $key ] <= 0 ) {
        break;
      }

        $current = $parentOf[ $key ];
        $depth++;
    }

      return $depth;
  }


  private function key( string $taxonomy, int $termId ): string {
      return $taxonomy . '#' . $termId;
  }


  private function fetch( string $column, array $values ): array {
      $placeholders = implode( ',', array_fill( 0, count( $values ), '%d' ) );
      $sql          = "
			SELECT term_taxonomy_id, term_id, taxonomy, parent
			FROM {$this->queryPrepare->prefix()}term_taxonomy
			WHERE $column IN ($placeholders)
		";
      $sql          = $this->queryPrepare->prepare( $sql, ...$values );

    try {
        $results = $this->queryHandler->query( $sql )->getResults();
    } catch ( DatabaseErrorException $e ) {
        return [];
    }

      $rows = [];
    foreach ( $results as $row ) {
        $rows[] = [
            'termTaxonomyId' => (int) $row['term_taxonomy_id'],
            'termId'         => (int) $row['term_id'],
            'taxonomy'       => $row['taxonomy'],
            'parent'         => (int) $row['parent'],
        ];
    }

      return $rows;
  }


}
