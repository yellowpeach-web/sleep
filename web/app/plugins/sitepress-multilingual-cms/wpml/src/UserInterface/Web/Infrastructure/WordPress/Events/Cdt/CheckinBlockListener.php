<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Events\Cdt;

use WPML\Core\Component\Cdt\Application\Service\CheckinBlock;

class CheckinBlockListener {

  private $checkinBlock;


  public function __construct( CheckinBlock $checkinBlock ) {
    $this->checkinBlock = $checkinBlock;
  }


  public function filter( $request ) {
    if ( ! is_array( $request ) ) {
      return $request;
    }

    try {
      return $this->checkinBlock->addTo( $request, time() );
    } catch ( \Throwable $e ) {
      return $request;
    }
  }


}
