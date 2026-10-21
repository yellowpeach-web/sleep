<?php

namespace WPML\Core\Component\SettingsStorage\Application\Service;

use WPML\Core\Component\SettingsStorage\Application\Repository\StorageQueryInterface;
use WPML\Core\Port\Persistence\OptionsInterface;

class VirtualOptionService {

  const LOGGED_LOST_KEYS = 'wpml_settings_storage_logged_lost_keys';

  const LOG_PREFIX = '[WPML][settings-storage]';

  private $options;

  private $query;

  private $diff;

  private $envelope;

  private $servedSnapshot = [];

  private $assembled = [];

  private $setterValues = [];

  private $writtenByRequest = [];

  private $registryKeys = [];

  private $logger;

  private $lostKeyDefaults = [];


  public function __construct(
    OptionsInterface $options,
    StorageQueryInterface $query,
    BlobDiffService $diff,
    RowEnvelope $envelope,
    ?callable $logger = null
  ) {
    $this->options  = $options;
    $this->query    = $query;
    $this->diff     = $diff;
    $this->envelope = $envelope;
    $this->logger   = $logger ?? static function ( string $message ): void {
      error_log( $message );
    };
  }


  public function restoreLostKeyTo( string $key, $default ) {
    $this->lostKeyDefaults[ $key ] = $default;
  }


  public function read( $storedValue, int $context ) {
    $keys = $this->registryKeys( $context );
    if ( $keys === null ) {
      return $storedValue;
    }

    if ( ! isset( $this->assembled[ $context ] ) ) {
      $this->assembled[ $context ] = $this->assemble( $keys, $storedValue );
    }

    return $this->assembled[ $context ];
  }


  public function recordSnapshot( $value, int $context ) {
    if ( ! isset( $this->servedSnapshot[ $context ] )
      && is_array( $value )
      && $this->registryKeys( $context ) !== null
    ) {
      $detached                         = unserialize( serialize( $value ) );
      $this->servedSnapshot[ $context ] = $detached;
    }

    return $value;
  }


  public function recordSetterValue( string $key, $value, int $context ) {
    $this->setterValues[ $context ][ $key ] = $value;
  }


  public function write( $incoming, int $context, $oldValue = null ) {
    $keys = $this->registryKeys( $context );
    if ( $keys === null && is_array( $incoming ) ) {
      $keys = $this->registryAppearedUnderneath( $context );
    }
    if ( $keys === null ) {
      unset( $this->setterValues[ $context ] );

      return $incoming;
    }

    if ( ! is_array( $incoming ) ) {
      return $incoming;
    }

    $base = $this->servedSnapshot[ $context ]
      ?? ( is_array( $oldValue ) ? $oldValue : $this->freshAssembly( $context ) );

    $diff = $this->diff->diff( $base, $incoming );
    $diff['changed'] += $this->setterReverts( $incoming, $context );
    $noop = $diff['changed'] === [] && $diff['deleted'] === [];

    if ( ! $noop && is_array( $oldValue ) ) {
      $assembly     = $this->assembled[ $context ] ?? $this->freshAssembly( $context );
      $assemblyDiff = $this->diff->diff( $assembly, $incoming );
      $noop         = $assemblyDiff['changed'] === [] && $assemblyDiff['deleted'] === [];
    }

    if ( $noop ) {
      if ( is_array( $oldValue ) ) {
        return $oldValue;
      }

      return $this->assembled[ $context ] ?? $this->freshAssembly( $context );
    }

    $scanned = $this->query->scanRowKeys();
    if ( $scanned !== [] ) {
      $this->registryKeys[ $context ] = $scanned;
      $keys                           = $scanned;
    }

    $newState = $this->diff->apply( $this->freshAssembly( $context ), $diff );

    foreach ( $diff['changed'] as $key => $keyValue ) {
      $this->writeRow( $key, $keyValue );
      $this->writtenByRequest[ $context ][ $key ] = $this->diff->fingerprint( $keyValue );
    }

    $newKeys = array_keys( $newState );
    if ( $newKeys !== $keys ) {
      $this->storeRegistry( $newKeys );
    }
    foreach ( $diff['deleted'] as $key ) {
      $this->options->delete( StorageQueryInterface::ROW_PREFIX . $key );
      $this->writtenByRequest[ $context ][ $key ] = null;
    }
    $this->forgetLogged( array_keys( $diff['changed'] ) );

    $this->registryKeys[ $context ] = $newKeys;
    $this->assembled[ $context ]    = $newState;

    return $newState;
  }


  private function setterReverts( array $incoming, int $context ): array {
    $reverts = [];
    foreach ( $this->setterValues[ $context ] ?? [] as $key => $value ) {
      if ( ! array_key_exists( $key, $incoming ) || ! $this->diff->same( $incoming[ $key ], $value ) ) {
        continue;
      }
      unset( $this->setterValues[ $context ][ $key ] );

      $written = $this->writtenByRequest[ $context ] ?? [];
      if ( array_key_exists( $key, $written ) && $written[ $key ] !== $this->diff->fingerprint( $value ) ) {
        $reverts[ $key ] = $incoming[ $key ];
      }
    }

    return $reverts;
  }


  public function writeRow( string $key, $value ) {
    $this->options->save( StorageQueryInterface::ROW_PREFIX . $key, $this->envelope->wrap( $value ), true );
  }


  public function delete( int $context ) {
    $keys = $this->registryKeys( $context );
    foreach ( (array) $keys as $key ) {
      $this->options->delete( StorageQueryInterface::ROW_PREFIX . $key );
    }
    foreach ( $this->query->scanRowKeys() as $key ) {
      $this->options->delete( StorageQueryInterface::ROW_PREFIX . $key );
    }
    $this->options->delete( StorageQueryInterface::REGISTRY_ROW );
    $this->options->delete( self::LOGGED_LOST_KEYS );
    $this->reset();
  }


  public function registryVanishedUnderneath( int $context ): bool {
    if ( ( $this->registryKeys[ $context ] ?? null ) === null ) {
      return false;
    }

    if ( $this->query->registryRowExistsInDatabase() ) {
      return false;
    }

    unset(
      $this->servedSnapshot[ $context ],
      $this->assembled[ $context ],
      $this->registryKeys[ $context ],
      $this->setterValues[ $context ],
      $this->writtenByRequest[ $context ]
    );

    return true;
  }


  private function registryAppearedUnderneath( int $context ): ?array {
    if ( ! $this->query->registryRowExistsInDatabase( false ) ) {
      return null;
    }

    $scanned = $this->query->scanRowKeys();
    if ( $scanned === [] ) {
      return null;
    }

    unset( $this->assembled[ $context ] );

    return $this->registryKeys[ $context ] = $scanned;
  }


  public function assembleFromRowsRaw(): ?array {
    $keys = $this->registryKeysFrom( $this->options->get( StorageQueryInterface::REGISTRY_ROW ) );
    if ( $keys === null ) {
      return null;
    }

    $state = [];
    foreach ( $keys as $key ) {
      $row = $this->options->get( StorageQueryInterface::ROW_PREFIX . $key );
      if ( is_array( $row ) && $this->envelope->isValidRow( $row ) ) {
        $state[ $key ] = $this->envelope->value( $row );
      }
    }

    return $state;
  }


  public function registryKeysFrom( $stored ): ?array {
    if ( ! is_array( $stored ) ) {
      return null;
    }
    $keys = $stored['keys'] ?? null;

    return is_array( $keys ) ? array_map( 'strval', array_values( $keys ) ) : null;
  }


  public function storeRegistry( array $keys, ?string $version = null ) {
    if ( $version === null ) {
      $stored  = $this->options->get( StorageQueryInterface::REGISTRY_ROW );
      $version = is_array( $stored ) && isset( $stored['version'] )
        ? (string) $stored['version']
        : ( defined( 'ICL_SITEPRESS_VERSION' ) ? ICL_SITEPRESS_VERSION : '' );
    }
    $this->options->save(
      StorageQueryInterface::REGISTRY_ROW,
      [ 'keys' => $keys, 'version' => $version ],
      true
    );
  }


  public function reset() {
    $this->servedSnapshot   = [];
    $this->assembled        = [];
    $this->registryKeys     = [];
    $this->setterValues     = [];
    $this->writtenByRequest = [];
  }


  public function refreshFromPersistence() {
    $this->assembled    = [];
    $this->registryKeys = [];
  }


  private function registryKeys( int $context ): ?array {
    if ( array_key_exists( $context, $this->registryKeys ) ) {
      return $this->registryKeys[ $context ];
    }

    $stored = $this->options->get( StorageQueryInterface::REGISTRY_ROW );
    $keys   = $this->registryKeysFrom( $stored );
    if ( $keys !== null ) {
      return $this->registryKeys[ $context ] = $keys;
    }

    if ( $stored === false && ! $this->query->optionRowExists( StorageQueryInterface::REGISTRY_ROW ) ) {
      return $this->registryKeys[ $context ] = null;
    }

    $scanned = $this->query->scanRowKeys();

    return $this->registryKeys[ $context ] = ( $scanned === [] ? null : $scanned );
  }


  private function assemble( array $keys, $storedValue = null ): array {
    $state       = [];
    $copy        = null;
    $leftMissing = [];
    foreach ( $keys as $key ) {
      $row = $this->options->get( StorageQueryInterface::ROW_PREFIX . $key );
      if ( is_array( $row ) && $this->envelope->isValidRow( $row ) ) {
        $state[ $key ] = $this->envelope->value( $row );
        continue;
      }

      if ( $copy === null ) {
        $copy = $this->materializedCopy( $storedValue );
      }
      if ( array_key_exists( $key, $copy ) ) {
        $state[ $key ] = $copy[ $key ];
        $this->writeRow( $key, $copy[ $key ] );
      } elseif ( array_key_exists( $key, $this->lostKeyDefaults ) ) {
        $state[ $key ] = $this->lostKeyDefaults[ $key ];
        $this->writeRow( $key, $state[ $key ] );
        $this->logRestoredToDefault( $key, $state[ $key ] );
      } else {
        $leftMissing[] = $key;
      }
    }

    if ( $copy !== null ) {
      $this->logLeftMissingOnce( $leftMissing );
    }

    return $state;
  }


  private function logRestoredToDefault( string $key, $default ) {
    ( $this->logger )(
      sprintf(
        '%s The setting "%s" lost its stored row and the saved copy does not hold it either; it is restored to its default (%s) and saved again.',
        self::LOG_PREFIX,
        $key,
        is_scalar( $default ) ? (string) $default : gettype( $default )
      )
    );
  }


  private function materializedCopy( $storedValue ): array {
    $copy = is_array( $storedValue ) ? $storedValue : $this->query->readRawStoredBlob();

    return is_array( $copy ) ? $copy : [];
  }


  private function logLeftMissingOnce( array $leftMissing ) {
    $stored = $this->options->get( self::LOGGED_LOST_KEYS, [] );
    $stored = is_array( $stored ) ? $stored : [];

    $logged = [];
    foreach ( $leftMissing as $key ) {
      $logged[ $key ] = 1;
      if ( ! isset( $stored[ $key ] ) ) {
        ( $this->logger )(
          sprintf(
            '%s The setting "%s" lost its stored row and the saved copy does not hold it either; it is served unset, so readers fall back to their own default. Logged once until the setting is saved again.',
            self::LOG_PREFIX,
            $key
          )
        );
      }
    }

    $this->storeLogged( $stored, $logged );
  }


  private function forgetLogged( array $writtenKeys ) {
    if ( $writtenKeys === [] ) {
      return;
    }
    $stored = $this->options->get( self::LOGGED_LOST_KEYS, [] );
    if ( ! is_array( $stored ) || $stored === [] ) {
      return;
    }

    $logged = $stored;
    foreach ( $writtenKeys as $key ) {
      unset( $logged[ $key ] );
    }
    $this->storeLogged( $stored, $logged );
  }


  private function storeLogged( array $stored, array $logged ) {
    if ( $logged === $stored ) {
      return;
    }
    if ( $logged === [] ) {
      $this->options->delete( self::LOGGED_LOST_KEYS );

      return;
    }
    $this->options->save( self::LOGGED_LOST_KEYS, $logged, true );
  }


  private function freshAssembly( int $context ): array {
    $keys = $this->registryKeys( $context );

    return $this->assemble( (array) $keys );
  }


}
