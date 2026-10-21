<?php

namespace WPML\UserInterface\Web\Legacy\Component\ATE;

use WPML\UserInterface\Web\Core\Component\ATE\Application\Endpoint\EateWidget\EateWidgetInterface;

use function WPML\Container\make;

class EateWidget implements EateWidgetInterface {


  public function getData(): array {
    $noCreditPopup = make( \WPML\TM\ATE\NoCreditPopup::class );
    return $noCreditPopup->getData();
  }


}
