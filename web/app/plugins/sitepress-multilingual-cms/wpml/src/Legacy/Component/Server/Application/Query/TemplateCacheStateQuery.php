<?php

namespace WPML\Legacy\Component\Server\Application\Query;

use WPML\Core\SharedKernel\Component\Server\Application\Query\TemplateCacheStateQueryInterface;
use WPML\Templates\TwigCacheState;

class TemplateCacheStateQuery implements TemplateCacheStateQueryInterface {

  private $twigCacheState;


  public function __construct( ?TwigCacheState $twigCacheState = null ) {
    $this->twigCacheState = $twigCacheState;
  }


  public function get(): array {
    if ( ! $this->twigCacheState ) {
      $this->twigCacheState = new TwigCacheState();
    }

    return $this->twigCacheState->read();
  }


}
