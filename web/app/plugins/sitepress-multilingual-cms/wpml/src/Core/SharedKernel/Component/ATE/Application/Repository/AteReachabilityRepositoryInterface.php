<?php

namespace WPML\Core\SharedKernel\Component\ATE\Application\Repository;

interface AteReachabilityRepositoryInterface {


  public function get(): ?bool;


  public function save( bool $reachable ): void;


}
