<?php

namespace WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\TranslateEverything;

use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\Core\SharedKernel\Component\ATE\Application\Service\AtePingerInterface;
use WPML\Core\SharedKernel\Component\ATE\Application\Service\TeaLoggerInterface;

class RecheckReachabilityController implements EndpointInterface {

  private $atePinger;

  private $logger;


  public function __construct(
    AtePingerInterface $atePinger,
    TeaLoggerInterface $logger
  ) {
    $this->atePinger = $atePinger;
    $this->logger    = $logger;
  }


  public function handle( $requestData = null ): array {
    $this->logger->beginReachabilityRecheck();

    try {
      $reachable = $this->atePinger->notifyTeaEnabled( AtePingerInterface::TRIGGER_RECHECK );

      if ( $reachable ) {
        update_option( 'wpml_ate_unreachable_since', 0, true );
      }

      return [ 'reachable' => $reachable ];
    } finally {
      $this->logger->end();
    }
  }


}
