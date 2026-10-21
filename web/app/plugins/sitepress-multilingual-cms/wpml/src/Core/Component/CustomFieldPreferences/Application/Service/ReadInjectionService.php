<?php

namespace WPML\Core\Component\CustomFieldPreferences\Application\Service;

use WPML\Core\Component\CustomFieldPreferences\Application\Repository\MigrationStateStorageInterface;
use WPML\Core\Component\CustomFieldPreferences\Application\Repository\PreferenceRepositoryInterface;
use WPML\Core\Component\CustomFieldPreferences\Domain\ElementType;
use WPML\Core\Component\CustomFieldPreferences\Domain\MapComposer;

final class ReadInjectionService {

  private $preferences;

  private $state;


  public function __construct(
    PreferenceRepositoryInterface $preferences,
    MigrationStateStorageInterface $state
  ) {
    $this->preferences = $preferences;
    $this->state       = $state;
  }


  public function injectOption( $value ) {
    if ( ! is_array( $value ) || ! $this->state->isMigrated() ) {
      return $value;
    }
    $tm = isset( $value[ ElementType::SETTINGS_KEY ] ) && is_array( $value[ ElementType::SETTINGS_KEY ] )
      ? $value[ ElementType::SETTINGS_KEY ]
      : [];

    $maps = [];
    foreach ( ElementType::BLOB_KEYS as $type => $blobKey ) {
      $maps[ $blobKey ] = $this->preferences->getMap( $type );
    }

    $value[ ElementType::SETTINGS_KEY ] = MapComposer::compose( $tm, $maps );
    return $value;
  }


}
