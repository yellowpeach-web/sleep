<?php

namespace WPML\Core\Component\CustomFieldPreferences\Application\Service;

use WPML\Core\Component\CustomFieldPreferences\Application\Repository\BlobMapsRepositoryInterface;
use WPML\Core\Component\CustomFieldPreferences\Application\Repository\MigrationStateStorageInterface;
use WPML\Core\Component\CustomFieldPreferences\Application\Repository\PreferenceRepositoryInterface;
use WPML\Core\Component\CustomFieldPreferences\Domain\ElementType;

final class RestoreService {

  private $blob;

  private $preferences;

  private $state;


  public function __construct(
    BlobMapsRepositoryInterface $blob,
    PreferenceRepositoryInterface $preferences,
    MigrationStateStorageInterface $state
  ) {
    $this->blob        = $blob;
    $this->preferences = $preferences;
    $this->state       = $state;
  }


  public function restore() {
    if ( ! $this->state->isMigrated() ) {
      return;
    }

    $maps = [];
    foreach ( ElementType::all() as $type ) {
      $maps[ $type ] = $this->preferences->getMap( $type );
    }

    if ( ! $this->blob->restore( $maps ) ) {
      return;
    }

    $this->state->disarm();
  }


}
