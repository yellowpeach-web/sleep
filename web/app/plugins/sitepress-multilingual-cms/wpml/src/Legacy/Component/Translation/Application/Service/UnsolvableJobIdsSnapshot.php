<?php

namespace WPML\Legacy\Component\Translation\Application\Service;

use WPML\Core\Component\Translation\Application\Service\UnsolvableJobIdsSnapshotInterface;

class UnsolvableJobIdsSnapshot implements UnsolvableJobIdsSnapshotInterface {


  public function forget( array $jobIds ) {
    if ( ! $jobIds ) {
      return;
    }

    \WPML\TM\ATE\PullDelivery\UnsolvableJobIds::forget( $jobIds );
  }


}
