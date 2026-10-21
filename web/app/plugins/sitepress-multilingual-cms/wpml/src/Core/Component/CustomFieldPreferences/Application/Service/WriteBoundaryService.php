<?php

namespace WPML\Core\Component\CustomFieldPreferences\Application\Service;

use WPML\Core\Component\CustomFieldPreferences\Application\Repository\BlobMapsReaderInterface;
use WPML\Core\Component\CustomFieldPreferences\Application\Repository\MigrationStateStorageInterface;
use WPML\Core\Component\CustomFieldPreferences\Application\Repository\PreferenceRepositoryInterface;
use WPML\Core\Component\CustomFieldPreferences\Application\Repository\StoreReadinessInterface;
use WPML\Core\Component\CustomFieldPreferences\Domain\ElementType;
use WPML\Core\Component\CustomFieldPreferences\Domain\MapDiff;
use WPML\Core\Component\CustomFieldPreferences\Domain\StoreIncidents;

use function WPML\PHP\Logger\error;

final class WriteBoundaryService {

  private $preferences;

  private $state;

  private $signal;

  private $brake;

  private $blobReader;

  private $readiness;

  private $storeIsReady;


  public function __construct(
    PreferenceRepositoryInterface $preferences,
    MigrationStateStorageInterface $state,
    PreferenceChangeSignal $signal,
    DeletionBrake $brake,
    BlobMapsReaderInterface $blobReader,
    StoreReadinessInterface $readiness
  ) {
    $this->preferences = $preferences;
    $this->state       = $state;
    $this->signal      = $signal;
    $this->brake       = $brake;
    $this->blobReader  = $blobReader;
    $this->readiness   = $readiness;
  }


  public function filter( $settings, $old = null ) {
    if (
      ! is_array( $settings )
      || empty( $settings[ ElementType::SETTINGS_KEY ] )
      || ! is_array( $settings[ ElementType::SETTINGS_KEY ] )
    ) {
      return $settings;
    }

    $migrationState = $this->state->read();

    $changed = $migrationState->isActive()
      ? $this->syncToStore( $settings, $migrationState->isMigrated() )
      : $this->blobWillChange( $settings, $old );

    if ( $changed ) {
      $this->signal->bump();
      $this->blobReader->resetRuntimeCache();
    }

    return $settings;
  }


  private function syncToStore( array &$settings, bool $migrated ): bool {
    $changed            = false;
    $this->storeIsReady = null;

    foreach ( ElementType::BLOB_KEYS as $type => $blobKey ) {
      $incoming = $settings[ ElementType::SETTINGS_KEY ][ $blobKey ] ?? null;
      if ( ! is_array( $incoming ) ) {
        continue;
      }

      $stored    = $this->preferences->getMap( $type );
      $diff      = MapDiff::between( $incoming, $stored );
      $lost      = 0;
      $attempted = false;

      if ( $diff->upserts() ) {
        $attempted = true;
        if ( $this->preferences->upsertMany( $type, $diff->upserts() ) ) {
          $changed = true;
        } else {
          $lost += count( $diff->upserts() );
        }
      }

      if (
        $diff->deletions()
        && ! $this->brake->refuses( $type, count( $diff->deletions() ), count( $stored ), $migrated )
      ) {
        $attempted = true;
        if ( $this->preferences->deleteMany( $type, $diff->deletions() ) ) {
          $changed = true;
        } else {
          $lost += count( $diff->deletions() );
        }
      }

      $this->reportOutcome( $type, $lost, $attempted, $diff );

      if ( $migrated && ! $lost ) {
        unset( $settings[ ElementType::SETTINGS_KEY ][ $blobKey ] );
      }
    }

    return $changed;
  }


  private function reportOutcome( string $type, int $lost, bool $attempted, MapDiff $diff ) {
    if ( $lost ) {
      $this->reportLoss( $type, $lost );
    } elseif ( $attempted || $diff->isEmpty() ) {
      $this->reportRecovery( $type );
    }
  }


  private function reportLoss( string $type, int $count ) {
    if ( $this->storeIsReady === null ) {
      $this->storeIsReady = $this->readiness->isReady();
    }

    $code = $this->storeIsReady ? StoreIncidents::WRITE_FAILED : StoreIncidents::UPGRADE_PENDING;

    error(
      sprintf(
        'WPML meta-settings save lost: the store refused %d %s preference entries (%s).',
        $count,
        $type,
        $code
      )
    );

    StoreIncidents::record( $code, $type, [ 'count' => $count ] );
  }


  private function reportRecovery( string $type ) {
    StoreIncidents::resolve( StoreIncidents::UPGRADE_PENDING, $type );
    StoreIncidents::resolve( StoreIncidents::WRITE_FAILED, $type );
    StoreIncidents::resolve( StoreIncidents::MODE_QUARANTINED, $type );
  }


  private function blobWillChange( array $settings, $old ): bool {
    $newTm = $this->tmSettingsOf( $settings );
    $oldTm = $this->tmSettingsOf( $old );

    foreach ( ElementType::BLOB_KEYS as $blobKey ) {
      $incoming = $newTm[ $blobKey ] ?? null;
      $current  = $oldTm[ $blobKey ] ?? null;

      $diff = MapDiff::between(
        is_array( $incoming ) ? $incoming : [],
        is_array( $current ) ? $current : []
      );

      if ( ! $diff->isEmpty() ) {
        return true;
      }
    }

    return false;
  }


  private function tmSettingsOf( $value ): array {
    $tm = is_array( $value ) ? ( $value[ ElementType::SETTINGS_KEY ] ?? null ) : null;

    return is_array( $tm ) ? $tm : [];
  }


}
