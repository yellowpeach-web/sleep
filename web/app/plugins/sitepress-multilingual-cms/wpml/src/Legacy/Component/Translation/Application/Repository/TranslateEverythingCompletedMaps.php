<?php

namespace WPML\Legacy\Component\Translation\Application\Repository;

use WPML\Core\Component\Translation\Application\Repository\TranslateEverythingCompletedMapsInterface;
use WPML\TM\ATE\TranslateEverything\CompletedMap;
use WPML\TM\ATE\TranslateEverything\UntranslatedPackages;

class TranslateEverythingCompletedMaps implements TranslateEverythingCompletedMapsInterface {


  public function removeTypes( string $map, array $types ) {
    if ( ! $types ) {
      return;
    }

    $isPackages = $map === TranslateEverythingCompletedMapsInterface::PACKAGES;

    CompletedMap::update(
      $map,
      function ( array $completed ) use ( $types, $isPackages ) {
        foreach ( $types as $type ) {
          unset( $completed[ $isPackages ? UntranslatedPackages::kindKey( $type ) : $type ] );
        }

        return $completed;
      }
    );
  }


}
