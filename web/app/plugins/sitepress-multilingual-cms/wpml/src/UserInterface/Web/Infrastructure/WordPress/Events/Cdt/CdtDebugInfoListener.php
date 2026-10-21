<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Events\Cdt;

use WPML\Core\Component\Cdt\Application\Service\AnswerReader;

class CdtDebugInfoListener {

  const SECTION = 'cdt';

  private $answerReader;


  public function __construct( AnswerReader $answerReader ) {
    $this->answerReader = $answerReader;
  }


  public function filter( $info ) {
    if ( ! is_array( $info ) ) {
      return $info;
    }

    $info[ self::SECTION ] = $this->answerReader->getDebugInfo();

    return $info;
  }


}
