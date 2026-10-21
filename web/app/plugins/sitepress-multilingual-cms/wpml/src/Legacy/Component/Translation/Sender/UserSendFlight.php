<?php

namespace WPML\Legacy\Component\Translation\Sender;

use WPML\Core\Component\Translation\Application\Service\UserSendFlightInterface;

class UserSendFlight implements UserSendFlightInterface {


  public function run( callable $action ) {
    return \WPML\TM\ATE\ClonedSites\ApiCommunication::duringUserSend( $action );
  }


}
