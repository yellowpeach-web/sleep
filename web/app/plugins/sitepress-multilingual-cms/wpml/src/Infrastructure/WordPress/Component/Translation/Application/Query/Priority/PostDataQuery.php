<?php

namespace WPML\Infrastructure\WordPress\Component\Translation\Application\Query\Priority;

use WPML\Core\Component\Translation\Application\Query\Priority\PostDataQueryInterface;
use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;
use WPML\Core\Port\Persistence\QueryHandlerInterface;
use WPML\Core\Port\Persistence\QueryPrepareInterface;

class PostDataQuery implements PostDataQueryInterface {

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


  public function getPostsData( array $postIds ): array {
    if ( empty( $postIds ) ) {
        return [];
    }
      $placeholders = implode( ',', array_fill( 0, count( $postIds ), '%d' ) );
      $sql          = "
			SELECT ID, post_type, post_parent, menu_order, post_title, post_modified_gmt
			FROM {$this->queryPrepare->prefix()}posts
			WHERE ID IN ($placeholders)
		";
      $sql          = $this->queryPrepare->prepare( $sql, ...$postIds );
    try {
        $results = $this->queryHandler->query( $sql )->getResults();
    } catch ( DatabaseErrorException $e ) {
        return [];
    }
      $postsData = [];
    foreach ( $results as $row ) {
        $postsData[] = [
            'ID'                => (int) $row['ID'],
            'post_type'         => $row['post_type'],
            'post_parent'       => (int) $row['post_parent'],
            'menu_order'        => (int) $row['menu_order'],
            'post_title'        => $row['post_title'],
            'post_modified_gmt' => $row['post_modified_gmt'],
            'is_featured'       => false,
            'stock_status'      => null,
        ];
    }
      return $postsData;
  }


  public function getParentMap( array $postIds ): array {
      $pending = array_values( array_unique( array_map( 'intval', $postIds ) ) );

    if ( empty( $pending ) ) {
        return [];
    }

      $parentMap = [];

    for ( $level = 0; $level < self::MAX_ANCESTOR_LEVELS && ! empty( $pending ); $level++ ) {
      foreach ( $this->fetchParents( $pending ) as $id => $parent ) {
          $parentMap[ $id ] = $parent;
      }

        $pending = $this->unresolvedAncestors( $parentMap );
    }

      return $parentMap;
  }


  private function unresolvedAncestors( array $parentMap ): array {
      $unresolved = [];

    foreach ( $parentMap as $parent ) {
      if ( $parent > 0 && ! array_key_exists( $parent, $parentMap ) ) {
          $unresolved[ $parent ] = true;
      }
    }

      return array_keys( $unresolved );
  }


  private function fetchParents( array $postIds ): array {
      $placeholders = implode( ',', array_fill( 0, count( $postIds ), '%d' ) );
      $sql          = "
			SELECT ID, post_parent
			FROM {$this->queryPrepare->prefix()}posts
			WHERE ID IN ($placeholders)
		";
      $sql          = $this->queryPrepare->prepare( $sql, ...$postIds );
    try {
        $results = $this->queryHandler->query( $sql )->getResults();
    } catch ( DatabaseErrorException $e ) {
        return [];
    }
      $parents = [];
    foreach ( $results as $row ) {
        $parents[ (int) $row['ID'] ] = (int) $row['post_parent'];
    }
      return $parents;
  }


}
