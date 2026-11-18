<?php

namespace WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\GetPosts;

use WPML\Core\Component\Post\Application\Query\Criteria\NoTargetLanguagesException;
use WPML\Core\Component\Post\Application\Query\Criteria\SearchCriteria;
use WPML\Core\Component\Post\Application\Query\Criteria\SearchCriteriaBuilder;
use WPML\Core\Component\Post\Application\Query\SearchQueryInterface;
use WPML\Core\Port\Appearance\AppearanceReaderInterface;
use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;
use WPML\PHP\Exception\Exception;
use WPML\PHP\Exception\InvalidArgumentException;


class GetPostsCountController implements EndpointInterface {

  private $findBySearchCriteriaQuery;

  private $criteriaBuilder;

  private $appearanceReader;


  public function __construct(
    SearchQueryInterface $findBySearchCriteriaQueryInterface,
    SearchCriteriaBuilder $criteriaBuilder,
    AppearanceReaderInterface $appearanceReader
  ) {
    $this->findBySearchCriteriaQuery = $findBySearchCriteriaQueryInterface;
    $this->criteriaBuilder           = $criteriaBuilder;
    $this->appearanceReader          = $appearanceReader;
  }


  public function handle( $requestData = null ): array {
    $requestData = $requestData ?: [];

    try {
      $criteria   = $this->criteriaBuilder->build( $requestData );
      $totalCount = $this->findBySearchCriteriaQuery->count( $criteria );

      return [
        'totalCount' => $totalCount,
        'notShown'   => $this->notShown( $criteria, $totalCount ),
      ];
    } catch ( NoTargetLanguagesException $e ) {
      return [
        'totalCount' => 0,
        'notShown'   => 0,
      ];
    } catch ( InvalidArgumentException $e ) {
      throw new InvalidArgumentException(
        'The request data for GetPostsCount is not valid.' . $e->getMessage()
      );
    }
  }


  private function notShown( SearchCriteria $criteria, int $totalCount ): int {
    if ( ! in_array( 'post_' . $criteria->getType(), $this->appearanceReader->heldTypes(), true ) ) {
      return 0;
    }

    $other = $this->findBySearchCriteriaQuery->count(
      $criteria->withAppearedOnly( ! $criteria->isAppearedOnly() )
    );

    $everyItem = $criteria->isAppearedOnly() ? $other : $totalCount;
    $appeared  = $criteria->isAppearedOnly() ? $totalCount : $other;

    return max( 0, $everyItem - $appeared );
  }


}
