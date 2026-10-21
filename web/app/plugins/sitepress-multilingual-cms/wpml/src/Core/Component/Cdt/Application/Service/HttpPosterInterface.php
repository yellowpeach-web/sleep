<?php

namespace WPML\Core\Component\Cdt\Application\Service;

interface HttpPosterInterface {


  public function post( array $fields, int $timeout ): array;


}
