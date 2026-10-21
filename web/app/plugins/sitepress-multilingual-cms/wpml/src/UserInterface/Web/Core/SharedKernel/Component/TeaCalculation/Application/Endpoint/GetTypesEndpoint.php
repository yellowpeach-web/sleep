<?php

namespace WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Endpoint;

use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\Core\SharedKernel\Component\Language\Application\ProspectiveLanguages;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Service\TypesService;

class GetTypesEndpoint implements EndpointInterface {

  private $service;

  private $prospectiveLanguages;


  public function __construct(
    TypesService $service,
    ProspectiveLanguages $prospectiveLanguages
  ) {
    $this->service = $service;
    $this->prospectiveLanguages = $prospectiveLanguages;
  }


  public function handle( $requestData = null ): array {
    $this->prospectiveLanguages->setFromRequest(
      is_array( $requestData ) && isset( $requestData['prospectiveLangs'] )
        ? $requestData['prospectiveLangs']
        : null
    );

    return $this->service->getTypesWithUntranslatedItems();
  }


}
