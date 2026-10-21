<?php

namespace WPML\Infrastructure\WordPress\Component\CustomFieldPreferences;

use WPML\Core\Component\CustomFieldPreferences\Application\Repository\MigrationStateStorageInterface;
use WPML\Core\Component\CustomFieldPreferences\Domain\MigrationState;
use WPML\Core\Port\Persistence\OptionsInterface;

class MigrationStateStorage implements MigrationStateStorageInterface {

  const MIGRATED_FLAG    = 'wpml_meta_settings_migrated';
  const STARTED_FLAG     = 'wpml_meta_settings_migration_started';
  const CUTOVER_DEADLINE = 'wpml_meta_settings_cutover_deadline';

  private $options;


  public function __construct( OptionsInterface $options ) {
    $this->options = $options;
  }


  public function read(): MigrationState {
    return new MigrationState(
      (bool) $this->options->get( self::STARTED_FLAG ),
      (bool) $this->options->get( self::MIGRATED_FLAG )
    );
  }


  public function isMigrated(): bool {
    return (bool) $this->options->get( self::MIGRATED_FLAG );
  }


  public function setMigrated( bool $migrated ) {
    $this->options->save( self::MIGRATED_FLAG, $migrated ? 1 : 0, true );
    if ( $migrated ) {
      $this->options->delete( self::CUTOVER_DEADLINE );
    }
  }


  public function markStarted() {
    $this->options->save( self::STARTED_FLAG, 1, true );
  }


  public function disarm() {
    $this->options->save( self::MIGRATED_FLAG, 0, true );
    $this->options->delete( self::STARTED_FLAG );
    $this->options->delete( self::CUTOVER_DEADLINE );
  }


  public function armCutoverDeadline( int $seconds ) {
    $this->options->add( self::CUTOVER_DEADLINE, time() + $seconds, false );
  }


  public function canCutOver(): bool {
    $deadline = $this->options->get( self::CUTOVER_DEADLINE, 0 );
    $deadline = is_numeric( $deadline ) ? (int) $deadline : 0;

    return $deadline === 0 || time() >= $deadline;
  }


}
