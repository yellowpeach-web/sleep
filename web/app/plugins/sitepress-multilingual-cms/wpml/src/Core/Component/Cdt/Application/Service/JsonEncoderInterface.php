<?php

namespace WPML\Core\Component\Cdt\Application\Service;

interface JsonEncoderInterface {


  public function encode( array $document ): string;


}
