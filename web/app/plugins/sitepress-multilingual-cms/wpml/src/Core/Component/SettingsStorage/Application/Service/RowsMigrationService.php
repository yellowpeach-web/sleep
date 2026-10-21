<?php

namespace WPML\Core\Component\SettingsStorage\Application\Service;

use WPML\Core\Component\CustomFieldPreferences\Application\Repository\MigrationStateStorageInterface;
use WPML\Core\Component\SettingsStorage\Application\Repository\StorageQueryInterface;
use WPML\Core\Port\Persistence\OptionsInterface;

class RowsMigrationService {

  private $virtualOption;

  private $query;

  private $options;

  private $customFieldsOffloadState;


  public function __construct(
    VirtualOptionService $virtualOption,
    StorageQueryInterface $query,
    OptionsInterface $options,
    MigrationStateStorageInterface $customFieldsOffloadState
  ) {
    $this->virtualOption            = $virtualOption;
    $this->query                    = $query;
    $this->options                  = $options;
    $this->customFieldsOffloadState = $customFieldsOffloadState;
  }


  public function runIfNeeded( string $version ) {
    $registry = $this->options->get( StorageQueryInterface::REGISTRY_ROW );

    if ( is_array( $registry ) && $this->virtualOption->registryKeysFrom( $registry ) !== null ) {
      if ( ( $registry['version'] ?? null ) === $version ) {
        return;
      }
      $this->resyncFromBlobIfDrifted( $version );

      return;
    }

    if ( $registry !== false ) {
      return;
    }

    $blob = $this->query->readRawStoredBlob();
    if ( ! is_array( $blob ) || $blob === [] ) {
      return;
    }

    if ( $this->waitsForOffload( $blob ) ) {
      return;
    }

    $this->explode( $blob, $version );
  }


  public function isWaitingForOffload(): bool {
    $blob = $this->query->readRawStoredBlob();
    if ( ! is_array( $blob ) || $blob === [] ) {
      return false;
    }

    return $this->waitsForOffload( $blob );
  }


  private function waitsForOffload( array $blob ): bool {
    return $this->customFieldMapsStillInBlob( $blob ) && ! $this->customFieldsOffloadState->isMigrated();
  }


  private function explode( array $blob, string $version ) {
    $keys = [];
    foreach ( $blob as $key => $value ) {
      if ( strpos( $key, '#' ) !== false ) {
        continue;
      }
      $this->virtualOption->writeRow( $key, $value );
      $keys[] = $key;
    }

    $this->virtualOption->storeRegistry( $keys, $version );
    $this->virtualOption->reset();
  }


  private function resyncFromBlobIfDrifted( string $version ) {
    $blob = $this->query->readRawStoredBlob();
    if ( ! is_array( $blob ) || $blob === [] ) {
      $this->restamp( $version );

      return;
    }

    $rows = $this->virtualOption->assembleFromRowsRaw();
    if ( $rows === null || serialize( $rows ) === serialize( $blob ) ) {
      $this->restamp( $version );

      return;
    }

    $this->explode( $blob, $version );
  }


  private function restamp( string $version ) {
    $keys = $this->virtualOption->registryKeysFrom( $this->options->get( StorageQueryInterface::REGISTRY_ROW ) );
    if ( $keys !== null ) {
      $this->virtualOption->storeRegistry( $keys, $version );
    }
  }


  private function customFieldMapsStillInBlob( array $blob ): bool {
    $tm = $blob['translation-management'] ?? null;
    if ( ! is_array( $tm ) ) {
      return false;
    }

    return ! empty( $tm['custom_fields_translation'] ) || ! empty( $tm['custom_term_fields_translation'] );
  }


}
