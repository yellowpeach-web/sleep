<?php

namespace WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Endpoint;

use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Repository\PendingTranslatableOfferRepositoryInterface;

class DiscardPendingTranslatableOffer implements EndpointInterface {

  private $repository;


  public function __construct( PendingTranslatableOfferRepositoryInterface $repository ) {
    $this->repository = $repository;
  }


  public function handle( $requestData = null ): array {
    $reverted = $this->repository->discard();

    return [
      'success'  => true,
      'reverted' => $reverted,
    ];
  }
}
