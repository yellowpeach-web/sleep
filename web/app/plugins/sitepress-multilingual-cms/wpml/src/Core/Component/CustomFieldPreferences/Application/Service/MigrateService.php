<?php

namespace WPML\Core\Component\CustomFieldPreferences\Application\Service;

use WPML\Core\Component\CustomFieldPreferences\Application\Repository\BlobMapsRepositoryInterface;
use WPML\Core\Component\CustomFieldPreferences\Application\Repository\MigrationStateStorageInterface;
use WPML\Core\Component\CustomFieldPreferences\Application\Repository\PreferenceRepositoryInterface;
use WPML\Core\Component\CustomFieldPreferences\Domain\ElementType;
use WPML\Core\Component\CustomFieldPreferences\Domain\MapDiff;
use WPML\Core\Component\CustomFieldPreferences\Domain\StorableKeys;
use WPML\Core\Component\CustomFieldPreferences\Domain\StoreIncidents;

use function WPML\PHP\Logger\error;

final class MigrateService {

  const SPOT_CHECKS = 20;

  private $blob;

  private $preferences;

  private $state;

  private $brake;

  private $normalizedMaps;


  public function __construct(
    BlobMapsRepositoryInterface $blob,
    PreferenceRepositoryInterface $preferences,
    MigrationStateStorageInterface $state,
    DeletionBrake $brake
  ) {
    $this->blob        = $blob;
    $this->preferences = $preferences;
    $this->state       = $state;
    $this->brake       = $brake;
  }


  public function markStarted() {
    $this->state->markStarted();
  }


  public function moveAll(): bool {
    foreach ( $this->getNormalizedMaps() as $type => $map ) {
      if ( ! $this->preferences->upsertMany( $type, $map ) ) {
        return false;
      }
      $this->reportStoreHoldsTheMap( $type );
    }

    return true;
  }


  private function reportStoreHoldsTheMap( string $type ) {
    StoreIncidents::resolve( StoreIncidents::UPGRADE_PENDING, $type );
    StoreIncidents::resolve( StoreIncidents::WRITE_FAILED, $type );
    StoreIncidents::resolve( StoreIncidents::BLOB_HEAL_FAILED, $type );
  }


  public function resolveLossesIfStoreHoldsTheBlob(): bool {
    if ( ! $this->verify() ) {
      return false;
    }

    foreach ( array_keys( $this->getNormalizedMaps() ) as $type ) {
      $this->reportStoreHoldsTheMap( $type );
    }
    $this->resolveHealFailures();

    return true;
  }


  public function resolveHealFailuresIfNothingLeftToHeal(): bool {
    if ( ! $this->state->isMigrated() && ! $this->verify() ) {
      return false;
    }

    $this->resolveHealFailures();

    return true;
  }


  private function resolveHealFailures() {
    foreach ( ElementType::all() as $type ) {
      StoreIncidents::resolve( StoreIncidents::BLOB_HEAL_FAILED, $type );
    }
    StoreIncidents::resolve( StoreIncidents::BLOB_HEAL_FAILED, implode( ',', ElementType::all() ) );
  }


  public function countSourceRecords(): int {
    $total = 0;
    foreach ( $this->getNormalizedMaps() as $map ) {
      $total += count( $map );
    }

    return $total;
  }


  public function ensureStoreMatchesBlob(): bool {
    if ( $this->resolveLossesIfStoreHoldsTheBlob() ) {
      return true;
    }

    if ( ! $this->healDrift() ) {
      return false;
    }

    return $this->verifyOrReport();
  }


  private function verifyOrReport(): bool {
    if ( $this->resolveLossesIfStoreHoldsTheBlob() ) {
      return true;
    }

    foreach ( $this->getNormalizedMaps() as $type => $map ) {
      $stored = $this->preferences->countByType( $type );
      error(
        sprintf(
          'WPML meta-settings verify failed after the heal: %d %s rows stored, %d expected.',
          $stored,
          $type,
          count( $map )
        )
      );
      StoreIncidents::record(
        StoreIncidents::BLOB_HEAL_FAILED,
        $type,
        [
          'stored'   => $stored,
          'expected' => count( $map ),
        ]
      );
    }

    return false;
  }


  private function healDrift(): bool {
    $maps    = $this->getNormalizedMaps();
    $restore = [];
    $healthy = true;

    foreach ( ElementType::all() as $type ) {
      $stored = $this->preferences->getMap( $type );

      if ( ! array_key_exists( $type, $maps ) ) {
        if ( $stored ) {
          $restore[ $type ] = $stored;
        }
        continue;
      }

      $diff = MapDiff::between( $maps[ $type ], $stored );

      if ( $diff->upserts() ) {
        $this->preferences->upsertMany( $type, $diff->upserts() );
      }

      if ( ! $diff->deletions() ) {
        continue;
      }

      if ( $this->brake->refuses( $type, count( $diff->deletions() ), count( $stored ), true ) ) {
        $healthy = false;
        continue;
      }

      $this->preferences->deleteMany( $type, $diff->deletions() );
    }

    if ( $restore ) {
      $healthy = $this->restoreBlobFrom( $restore ) && $healthy;
    }

    return $healthy;
  }


  private function restoreBlobFrom( array $mapsByType ): bool {
    $counts = [];
    foreach ( $mapsByType as $type => $map ) {
      $counts[ $type ] = count( $map );
    }
    $total = array_sum( $counts );

    if ( ! $this->blob->restoreMaps( $mapsByType ) ) {
      error(
        sprintf(
          'WPML meta-settings could not restore %d preference(s) into the settings after a map-less save: %s.',
          $total,
          implode( ', ', array_keys( $counts ) )
        )
      );
      StoreIncidents::record(
        StoreIncidents::BLOB_HEAL_FAILED,
        implode( ',', array_keys( $counts ) ),
        [ 'expected' => $total ]
      );

      return false;
    }

    $this->normalizedMaps = null;

    error(
      sprintf(
        'WPML meta-settings restored %d preference(s) into the settings after a save that carried no field maps.',
        $total
      )
    );
    StoreIncidents::record(
      StoreIncidents::BLOB_HEALED,
      implode( ',', array_keys( $counts ) ),
      [ 'count' => $total ]
    );

    return true;
  }


  public function verify(): bool {
    $maps = $this->getNormalizedMaps();

    foreach ( ElementType::all() as $type ) {
      $stored = $this->preferences->countByType( $type );

      if ( ! array_key_exists( $type, $maps ) ) {
        if ( $stored !== 0 ) {
          return false;
        }
        continue;
      }

      $map = $maps[ $type ];
      if ( $stored !== count( $map ) ) {
        return false;
      }
      $names = array_keys( $map );
      if ( $names ) {
        $sample = array_intersect_key(
          $names,
          array_flip( (array) array_rand( $names, min( self::SPOT_CHECKS, count( $names ) ) ) )
        );
        $this->preferences->resetRuntimeCache();
        foreach ( $sample as $name ) {
          if ( $this->preferences->getMode( $type, (string) $name ) !== $map[ $name ] ) {
            return false;
          }
        }
      }
    }
    return true;
  }


  public function cutover() {
    $this->state->setMigrated( true );
    $this->blob->resaveForCutover();
  }


  private function getNormalizedMaps(): array {
    if ( $this->normalizedMaps !== null ) {
      return $this->normalizedMaps;
    }

    $raw  = $this->blob->getMaps();
    $maps = [];
    foreach ( ElementType::all() as $type ) {
      if ( ! array_key_exists( $type, $raw ) ) {
        continue;
      }
      $map = $raw[ $type ];
      unset( $map[''] );
      ksort( $map, SORT_STRING );
      $map      = array_map( 'intval', $map );
      $storable = StorableKeys::filter( $map );
      StorableKeys::logDropped( 'migration', $type, StorableKeys::dropped( $map, $storable ) );
      $maps[ $type ] = $storable;
    }
    $this->normalizedMaps = $maps;

    return $maps;
  }


}
