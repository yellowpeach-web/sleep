<?php

namespace WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Endpoint;

use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Repository\ParkedTypesOfferRepositoryInterface;

class DismissParkedTypesOffer implements EndpointInterface {

  private $repository;


  public function __construct( ParkedTypesOfferRepositoryInterface $repository ) {
    $this->repository = $repository;
  }


  public function handle( $requestData = null ): array {
    $types = isset( $requestData['types'] ) && is_array( $requestData['types'] )
      ? array_values( array_filter( $requestData['types'], 'is_string' ) )
      : [];

    if ( $types === [] ) {
      return [
        'success' => false,
        'message' => 'Types array is required.',
      ];
    }

    $this->repository->dismiss( $types );

    return [
      'success'   => true,
      'dismissed' => $types,
    ];
  }
}
