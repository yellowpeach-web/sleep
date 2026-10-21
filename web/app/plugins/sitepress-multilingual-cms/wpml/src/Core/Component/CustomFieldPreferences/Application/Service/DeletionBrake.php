<?php

namespace WPML\Core\Component\CustomFieldPreferences\Application\Service;

use WPML\Core\Component\CustomFieldPreferences\Domain\StoreIncidents;

use function WPML\PHP\Logger\error;

final class DeletionBrake {

  const DELETE_BRAKE_MIN_ROWS = 200;


  public function refuses( string $type, int $deletions, int $storedTotal, bool $armed ): bool {
    $refuses = $armed
      && $deletions >= self::DELETE_BRAKE_MIN_ROWS
      && $deletions * 2 > $storedTotal;

    if ( $refuses ) {
      error(
        sprintf(
          'WPML meta-settings write brake: refused to delete %d of %d stored %s preferences in one save.',
          $deletions,
          $storedTotal,
          $type
        )
      );
      StoreIncidents::record(
        StoreIncidents::DELETION_REFUSED,
        $type,
        [
          'deletions' => $deletions,
          'stored'    => $storedTotal,
        ]
      );
    }

    return $refuses;
  }


}
