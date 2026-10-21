<?php

namespace WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Endpoint;

use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Service\TypesService;

class PersistOfferedTypeSinceDates implements EndpointInterface {

  private $service;


  public function __construct( TypesService $service ) {
    $this->service = $service;
  }


  public function handle( $requestData = null ): array {
    if (
      is_array( $requestData )
      && ! empty( $requestData['prospectiveLangs'] )
    ) {
      return [
        'success' => false,
        'message' => 'Offered type defaults are not persisted during a cost estimate.',
      ];
    }

    $this->service->persistOfferedTypeDefaults();

    return [
      'success' => true,
      'message' => 'Offered type defaults persisted.',
    ];
  }


}
