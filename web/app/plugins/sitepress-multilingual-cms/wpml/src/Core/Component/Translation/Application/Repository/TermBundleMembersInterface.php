<?php

namespace WPML\Core\Component\Translation\Application\Repository;

interface TermBundleMembersInterface {

  const STATE_DROPPED = 'dropped';


  public function unsolved( int $bundleJobId ): array;


  public function setState( int $bundleJobId, array $termRids, string $state );


  public function releaseWords( int $bundleJobId, array $termRids );


  public function hasApplied( int $bundleJobId ): bool;


  public function completeDelivered( int $bundleJobId, bool $clearErrorRecord = true );


}
