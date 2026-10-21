<?php

namespace WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Endpoint;

use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Service\TypesService;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Validator\TypeIncludeRules;


class UpdateTypesInclude implements EndpointInterface {

  private $service;

  private $rules;


  public function __construct( TypesService $service, TypeIncludeRules $rules ) {
    $this->service = $service;
    $this->rules   = $rules;
  }


  public function handle( $requestData = null ): array {
    $type = $requestData['type'] ?? null;
    $includeSince = $requestData['includeSince'] ?? null;
    $kind = $requestData['kind'] ?? null;

    $reason = $this->rules->validate( $type, $includeSince, $kind );
    if ( $reason !== null ) {
      return [
        'success' => false,
        'message' => $reason,
      ];
    }
    assert( is_string( $type ) && is_string( $includeSince ) );

    $this->service->updateTypeSinceDate( $type, $includeSince );

    return [
      'success' => true,
      'message' => 'Type since date updated successfully.',
    ];
  }


}
