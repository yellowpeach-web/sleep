<?php

namespace WPML\Core\Component\CustomFieldPreferences\Domain;

final class MigrationState {

  private $started;

  private $migrated;


  public function __construct( bool $started, bool $migrated ) {
    $this->started  = $started;
    $this->migrated = $migrated;
  }


  public function isMigrated(): bool {
    return $this->migrated;
  }


  public function isMirroring(): bool {
    return $this->started && ! $this->migrated;
  }


  public function isActive(): bool {
    return $this->started || $this->migrated;
  }


}
