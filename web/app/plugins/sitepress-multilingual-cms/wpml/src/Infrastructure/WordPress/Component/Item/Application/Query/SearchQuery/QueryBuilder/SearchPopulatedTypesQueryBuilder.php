<?php

namespace WPML\Infrastructure\WordPress\Component\Item\Application\Query\SearchQuery\QueryBuilder;

use WPML\Core\Component\Post\Application\Query\Criteria\SearchPopulatedTypesCriteria;
use WPML\Core\Port\Persistence\QueryPrepareInterface;

class SearchPopulatedTypesQueryBuilder {
  use SearchQueryBuilderTrait;

  private $queryPrepare;


  public function __construct( QueryPrepareInterface $queryPrepare ) {
    $this->queryPrepare = $queryPrepare;
  }


  public function build( SearchPopulatedTypesCriteria $criteria, string $postTypeId ): string {
    return "
      SELECT p.post_type
      {$this->buildPostsWithSourceTranslation( $criteria->getSourceLanguageCode() )}
      WHERE p.post_type = {$this->queryPrepare->prepare( '%s', $postTypeId )}
        {$this->buildPostStatusCondition( $criteria->getPublicationStatus() )}
        {$this->buildExcludedByPostMetaCondition( $postTypeId )}
        AND {$this->buildTranslationStatusCondition( $criteria )}
      LIMIT 1
    ";
  }


}
