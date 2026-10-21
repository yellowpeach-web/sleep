<?php

namespace WPML\Infrastructure\WordPress\Component\Item\Application\Query;

use WPML\Core\Component\Post\Application\Query\Criteria\SearchPopulatedTypesCriteria as SearchCriteria;
use WPML\Core\Component\Post\Application\Query\SearchPopulatedTypesQueryInterface;
use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;
use WPML\Core\Port\Persistence\QueryHandlerInterface;
use WPML\Infrastructure\WordPress\Component\Item\Application\Query\SearchQuery\QueryBuilder\SearchPopulatedTypesQueryBuilder;

class SearchPopulatedTypesQuery implements SearchPopulatedTypesQueryInterface {

  private $queryBuilder;

  private $queryHandler;


  public function __construct(
    SearchPopulatedTypesQueryBuilder $queryBuilder,
    QueryHandlerInterface $queryHandler
  ) {
    $this->queryBuilder = $queryBuilder;
    $this->queryHandler = $queryHandler;
  }


  public function get( SearchCriteria $criteria ): array {
    $postTypes = $criteria->getPostTypeIds();
    foreach ( $postTypes as $postTypeIndex => $postType ) {
      $query = $this->queryBuilder->build( $criteria, $postType );

      if ( ! $this->queryHandler->querySingle( $query ) ) {
        unset( $postTypes[ $postTypeIndex ] );
      }
    }

    return $postTypes;
  }


}
