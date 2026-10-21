<?php

namespace WPML\Core\Component\CustomFieldPreferences\Application\Service;

use WPML\Core\Component\CustomFieldPreferences\Application\Repository\MigrationStateStorageInterface;
use WPML\Core\Component\CustomFieldPreferences\Application\Repository\PreferenceRepositoryInterface;
use WPML\Core\Component\CustomFieldPreferences\Domain\StorableKeys;

final class PreferenceWriteService {

  private $preferences;

  private $state;

  private $signal;


  public function __construct(
    PreferenceRepositoryInterface $preferences,
    MigrationStateStorageInterface $state,
    PreferenceChangeSignal $signal
  ) {
    $this->preferences = $preferences;
    $this->state       = $state;
    $this->signal      = $signal;
  }


  public function setModes( string $type, array $nameToMode ): bool {
    if ( ! $this->state->isMigrated() ) {
      return false;
    }

    $modes    = array_map( 'intval', $nameToMode );
    $storable = StorableKeys::filter( $modes );
    StorableKeys::logDropped( 'write', $type, StorableKeys::dropped( $modes, $storable ) );

    $changed = [];
    foreach ( $storable as $name => $mode ) {
      if ( $this->preferences->getMode( $type, (string) $name ) !== $mode ) {
        $changed[ $name ] = $mode;
      }
    }
    if ( ! $changed ) {
      return true;
    }

    if ( ! $this->preferences->upsertMany( $type, $changed ) ) {
      return false;
    }
    $this->signal->bump();

    return true;
  }


  public function deleteNames( string $type, array $names ): bool {
    if ( ! $this->state->isMigrated() ) {
      return false;
    }

    $names = array_values( array_unique( array_map( 'strval', $names ) ) );
    if ( ! $names ) {
      return true;
    }

    if ( ! $this->preferences->deleteMany( $type, $names ) ) {
      return false;
    }
    $this->signal->bump();

    return true;
  }


}
