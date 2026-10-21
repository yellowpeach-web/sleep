<?php

namespace WPML\Core\Component\MinimumRequirements\Application\Repository;

interface RequirementsRefreshLockInterface {


  public function acquire(): bool;


  public function release();


}
