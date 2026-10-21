<?php

namespace WPML\Core\Component\CustomFieldPreferences\Application\Service;

use WPML\Core\Component\CustomFieldPreferences\Application\Repository\BlobMapsReaderInterface;
use WPML\Core\Component\CustomFieldPreferences\Application\Repository\MigrationStateStorageInterface;
use WPML\Core\Component\CustomFieldPreferences\Application\Repository\PreferenceRepositoryInterface;
use WPML\Core\Component\CustomFieldPreferences\Domain\ElementType;

final class PreferenceMapsService {

  private $preferences;

  private $blobMaps;

  private $state;


  public function __construct(
    PreferenceRepositoryInterface $preferences,
    BlobMapsReaderInterface $blobMaps,
    MigrationStateStorageInterface $state
  ) {
    $this->preferences = $preferences;
    $this->blobMaps    = $blobMaps;
    $this->state       = $state;
  }


  public function getMap( string $type ): array {
    if ( $this->state->isMigrated() ) {
      return $this->preferences->getMap( $type );
    }

    $maps = $this->blobMaps->getMaps();
    $map  = isset( $maps[ $type ] ) ? $maps[ $type ] : null;

    return \is_array( $map ) ? $map : [];
  }


  public function getAllMaps(): array {
    if ( $this->state->isMigrated() ) {
      return $this->preferences->getMaps();
    }

    $maps   = $this->blobMaps->getMaps();
    $result = [];
    foreach ( ElementType::all() as $type ) {
      $map              = isset( $maps[ $type ] ) ? $maps[ $type ] : null;
      $result[ $type ] = \is_array( $map ) ? $map : [];
    }

    return $result;
  }


  public function getMode( string $type, string $name ) {
    if ( $this->state->isMigrated() ) {
      return $this->preferences->getMode( $type, $name );
    }

    $map = $this->getMap( $type );

    return isset( $map[ $name ] ) ? (int) $map[ $name ] : null;
  }


  public function getModes( string $type, array $names ): array {
    if ( $this->state->isMigrated() ) {
      return $this->preferences->getModes( $type, $names );
    }

    $map   = $this->getMap( $type );
    $modes = [];
    foreach ( $names as $name ) {
      $name           = (string) $name;
      $modes[ $name ] = isset( $map[ $name ] ) ? (int) $map[ $name ] : null;
    }

    return $modes;
  }


}
