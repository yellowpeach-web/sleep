<?php

namespace WPML\Core\Component\CustomFieldPreferences\Domain;

final class MapComposer {


  public static function compose( array $tmSettings, array $mapsByBlobKey ): array {
    foreach ( $mapsByBlobKey as $blobKey => $map ) {
      $inMemory = isset( $tmSettings[ $blobKey ] ) && is_array( $tmSettings[ $blobKey ] )
        ? $tmSettings[ $blobKey ]
        : [];

      $tmSettings[ $blobKey ] = $inMemory + $map;
    }

    return $tmSettings;
  }


}
