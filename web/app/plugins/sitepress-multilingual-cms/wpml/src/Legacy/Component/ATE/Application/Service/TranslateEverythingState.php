<?php

namespace WPML\Legacy\Component\ATE\Application\Service;

use WPML\Core\SharedKernel\Component\ATE\Application\Service\TranslateEverythingStateInterface;
use WPML\TM\ATE\TranslateEverything;

class TranslateEverythingState implements TranslateEverythingStateInterface {

  private $translateEverything;


  public function __construct( TranslateEverything $translateEverything ) {
    $this->translateEverything = $translateEverything;
  }


  public function isEverythingProcessed(): bool {
    return $this->translateEverything->isEverythingProcessed();
  }


}
