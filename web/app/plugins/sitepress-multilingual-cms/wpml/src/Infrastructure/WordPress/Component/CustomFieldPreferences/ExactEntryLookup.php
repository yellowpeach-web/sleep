<?php

namespace WPML\Infrastructure\WordPress\Component\CustomFieldPreferences;

use WPML\Core\Component\CustomFieldPreferences\Application\Service\ExactEntryLookupInterface;
use WPML\Core\Component\CustomFieldPreferences\Application\Service\PreferenceMapsService;

class ExactEntryLookup implements ExactEntryLookupInterface {

  private $maps;


  public function __construct( PreferenceMapsService $maps ) {
    $this->maps = $maps;
  }


  public function hasExactEntry( string $type, string $name ): bool {
    return $this->maps->getMode( $type, $name ) !== null;
  }


  public function hasExactEntries( string $type, array $names ): array {
    $exact = [];
    $batch = [];
    foreach ( $names as $name ) {
      if ( $name !== rtrim( $name, ' ' ) ) {
        $exact[ $name ] = $this->hasExactEntry( $type, $name );
      } else {
        $batch[] = $name;
      }
    }

    $modes = $batch ? $this->maps->getModes( $type, $batch ) : [];
    foreach ( $batch as $name ) {
      $exact[ $name ] = isset( $modes[ $name ] );
    }

    return $exact;
  }


}
