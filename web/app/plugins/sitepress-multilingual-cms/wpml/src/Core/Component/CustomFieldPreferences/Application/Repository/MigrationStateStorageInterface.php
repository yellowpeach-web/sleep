<?php

namespace WPML\Core\Component\CustomFieldPreferences\Application\Repository;

use WPML\Core\Component\CustomFieldPreferences\Domain\MigrationState;

interface MigrationStateStorageInterface {


  public function read(): MigrationState;


  public function isMigrated(): bool;


  public function setMigrated( bool $migrated );


  public function markStarted();


  public function disarm();


  public function armCutoverDeadline( int $seconds );


  public function canCutOver(): bool;


}
