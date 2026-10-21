<?php

namespace WPML\Core\Component\Cdt\Application\Service;

interface MapJsonEncoderInterface {


  public function encode( array $document ): string;


}
