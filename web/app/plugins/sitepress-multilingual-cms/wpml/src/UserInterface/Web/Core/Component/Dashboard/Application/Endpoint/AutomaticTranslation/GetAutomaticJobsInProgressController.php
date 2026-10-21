<?php

namespace WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\AutomaticTranslation;

use WPML\Core\Component\Translation\Application\Query\JobQueryInterface;
use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;

class GetAutomaticJobsInProgressController implements EndpointInterface {

  private $jobQuery;


  public function __construct( JobQueryInterface $jobQuery ) {
    $this->jobQuery = $jobQuery;
  }


  public function handle( $requestData = null ): array {
    $results = [
      'success'  => true,
      'data'     => null,
      'errorMsg' => '',
    ];

    try {
      $results['data'] = [
        'automaticJobsInProgressCount' => $this->jobQuery->countAutomaticInProgress(),
        'chargedJobsInProgress'        => $this->jobQuery->getInFlightChargedAutomatic(),
      ];
    } catch ( DatabaseErrorException $exception ) {
      $results['success']  = false;
      $results['errorMsg'] = 'Database Error: ' . $exception->getMessage();
    }

    return $results;
  }


}
