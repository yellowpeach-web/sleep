<?php

namespace WPML\Core\Component\SettingsStorage\Application\Service;

use WPML\Core\Component\SettingsStorage\Application\Repository\StorageQueryInterface;
use WPML\Core\Port\Persistence\OptionsInterface;

class VirtualOptionService {

  const LOGGED_LOST_KEYS = 'wpml_settings_storage_logged_lost_keys';

  const LOG_PREFIX = '[WPML][settings-storage]';

  const SCAN_LINE = "\0scan";

  const REGISTRY_LINE = "\0registry";

  private $options;

  private $query;

  private $diff;

  private $envelope;

  private $servedSnapshot = [];

  private $assembled = [];

  private $setterValues = [];

  private $writtenByRequest = [];

  private $registryKeys = [];

  private $registryReadable = [];

  private $logger;

  private $lostKeyDefaults = [];

  private $unreadableKeys = [];

  private $refusalLogged = [];

  private $shortRegistry = [];

  private $servedRowKeys = [];

  private $registryUnreadable = [];

  private $servedOnUnreadableList = [];


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


  public function servesFromRows( int $context ): bool {
    return $this->registryKeys( $context ) !== null && ! empty( $this->registryReadable[ $context ] );
  }


  public function read( $storedValue, int $context ) {
    $keys = $this->registryKeys( $context );
    if ( $keys === null ) {
      if ( ! empty( $this->registryUnreadable[ $context ] ) ) {
        $this->servedOnUnreadableList[ $context ] = is_array( $storedValue )
          ? array_fill_keys( array_map( 'strval', array_keys( $storedValue ) ), true )
          : [];
      }

      return $storedValue;
    }

    if ( ! isset( $this->assembled[ $context ] ) ) {
      unset( $this->servedOnUnreadableList[ $context ] );
      $this->assembled[ $context ] = $this->assemble( $keys, $storedValue, $context );
      if ( ! isset( $this->servedRowKeys[ $context ] ) ) {
        $this->servedRowKeys[ $context ] = array_keys( $this->assembled[ $context ] );
      }
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
    if ( $keys === null && isset( $this->servedSnapshot[ $context ] ) ) {
      if ( isset( $this->registryUnreadable[ $context ] ) ) {
        $this->logRefusedOnce( $context, self::REGISTRY_LINE, 'The settings list could not be read in this save; the save is refused and the stored settings are kept.' );

        return $oldValue;
      }
      $this->forgetContext( $context );
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

    foreach ( $this->refusedUnreadableKeys( array_keys( $diff['changed'] ), $context, $base ) as $key ) {
      unset( $diff['changed'][ $key ], $incoming[ $key ] );
    }

    $noop = $diff['changed'] === [] && $diff['deleted'] === [];

    if ( ! $noop && is_array( $oldValue ) ) {
      $assembly     = $this->assembled[ $context ] ?? $this->freshAssembly( $context );
      $assemblyDiff = $this->diff->diff( $assembly, $incoming );
      $noop         = $assemblyDiff['changed'] === [] && $assemblyDiff['deleted'] === [];
    }

    if ( $noop ) {
      if ( isset( $this->shortRegistry[ $context ] ) ) {
        $this->storeRegistry( (array) $this->registryKeys[ $context ] );
        unset( $this->shortRegistry[ $context ] );
      }

      if ( is_array( $oldValue ) ) {
        return $oldValue;
      }

      return $this->assembled[ $context ] ?? $this->freshAssembly( $context );
    }

    $listedKeys = $keys;
    $servedKeys = array_merge( $keys, $this->servedRowKeys[ $context ] ?? [] );
    if ( isset( $this->shortRegistry[ $context ] ) ) {
      $listedKeys = $this->shortRegistry[ $context ];
      unset( $this->shortRegistry[ $context ] );
    }
    $scanned = $this->settingKeys( $this->query->scanRowKeys() );
    if ( $scanned === [] && $keys !== [] ) {
      $this->logUnusedScanOnce( $context );
    }
    if ( $scanned !== [] ) {
      $this->registryKeys[ $context ] = $scanned;
      $keys                           = $scanned;
    }

    $newState = $this->diff->apply( $this->freshAssembly( $context ), $diff );

    foreach ( $diff['changed'] as $key => $keyValue ) {
      $this->writeRow( $key, $keyValue );
      $this->writtenByRequest[ $context ][ $key ] = $this->diff->fingerprint( $keyValue );
      unset( $this->unreadableKeys[ $context ][ $key ] );
    }

    $newKeys = array_keys( $newState );
    foreach ( array_unique( array_merge( $keys, $servedKeys ) ) as $key ) {
      if ( ! array_key_exists( $key, $newState ) && ! in_array( $key, $diff['deleted'], true ) ) {
        $newKeys[] = $key;
      }
    }
    if ( ! $this->sameKeySet( $newKeys, $listedKeys ) ) {
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


  private function refusedUnreadableKeys( array $changedKeys, int $context, array $base ): array {
    $refused = [];
    $written = $this->writtenByRequest[ $context ] ?? [];
    foreach ( $changedKeys as $key ) {
      $key        = (string) $key;
      $unreadable = isset( $this->unreadableKeys[ $context ][ $key ] );
      $neverRead  = ! array_key_exists( $key, $base ) && ! array_key_exists( $key, $written );
      if ( ( ! $unreadable && ! $neverRead )
        || ! $this->envelope->isValidRow( $this->query->readRawRow( $key ) )
      ) {
        continue;
      }
      $refused[] = $key;
      if ( ! isset( $this->refusalLogged[ $context ][ $key ] ) ) {
        $this->refusalLogged[ $context ][ $key ] = true;
        ( $this->logger )(
          sprintf(
            '%s The setting "%s" is stored, but this request could not read it; a save that would replace it is refused and the stored value is kept.',
            self::LOG_PREFIX,
            $key
          )
        );
      }
    }

    return $refused;
  }


  public function isStoredButUnreadable( string $key, int $context ): bool {
    $leftMissing     = isset( $this->unreadableKeys[ $context ][ $key ] );
    $missingFromCopy = isset( $this->servedOnUnreadableList[ $context ] )
      && ! isset( $this->servedOnUnreadableList[ $context ][ $key ] );
    if ( ! $leftMissing && ! $missingFromCopy ) {
      return false;
    }

    return $this->envelope->isValidRow( $this->query->readRawRow( $key ) );
  }


  private function logUnusedScanOnce( int $context ) {
    $this->logRefusedOnce( $context, self::SCAN_LINE, 'The settings row scan of this save read no row name; it is not used, and the save keeps the settings list this request was served.' );
  }


  private function logRefusedOnce( int $context, string $entry, string $message ) {
    if ( isset( $this->refusalLogged[ $context ][ $entry ] ) ) {
      return;
    }
    $this->refusalLogged[ $context ][ $entry ] = true;
    ( $this->logger )( self::LOG_PREFIX . ' ' . $message );
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

    $this->forgetContext( $context );

    return true;
  }


  private function forgetContext( int $context ) {
    unset(
      $this->servedSnapshot[ $context ],
      $this->assembled[ $context ],
      $this->registryKeys[ $context ],
      $this->setterValues[ $context ],
      $this->writtenByRequest[ $context ],
      $this->unreadableKeys[ $context ],
      $this->refusalLogged[ $context ],
      $this->shortRegistry[ $context ],
      $this->servedRowKeys[ $context ],
      $this->registryUnreadable[ $context ],
      $this->servedOnUnreadableList[ $context ]
    );
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
    if ( ! is_array( $keys ) ) {
      return null;
    }

    $names = [];
    foreach ( $keys as $key ) {
      if ( is_int( $key ) || ( is_string( $key ) && $key !== '' ) ) {
        $names[] = (string) $key;
      }
    }

    return $names === [] && $keys !== [] ? null : $names;
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
    $this->servedSnapshot         = [];
    $this->assembled              = [];
    $this->registryKeys           = [];
    $this->setterValues           = [];
    $this->writtenByRequest       = [];
    $this->unreadableKeys         = [];
    $this->refusalLogged          = [];
    $this->shortRegistry          = [];
    $this->servedRowKeys          = [];
    $this->registryUnreadable     = [];
    $this->servedOnUnreadableList = [];
  }


  public function refreshFromPersistence() {
    $this->assembled          = [];
    $this->registryKeys       = [];
    $this->registryReadable   = [];
    $this->shortRegistry      = [];
    $this->registryUnreadable = [];
  }


  private function registryKeys( int $context ): ?array {
    if ( array_key_exists( $context, $this->registryKeys ) ) {
      return $this->registryKeys[ $context ];
    }

    unset( $this->registryUnreadable[ $context ] );
    $stored = $this->options->get( StorageQueryInterface::REGISTRY_ROW );
    $keys   = $this->registryKeysFrom( $stored );

    $this->registryReadable[ $context ] = $keys !== null;
    if ( $keys !== null ) {
      return $this->registryKeys[ $context ] = $this->withUnlistedRows( $keys, $context );
    }

    if ( $stored === false && ! $this->query->optionRowExists( StorageQueryInterface::REGISTRY_ROW ) ) {
      return $this->registryKeys[ $context ] = null;
    }

    $scanned = $this->settingKeys( $this->query->scanRowKeys() );
    if ( $scanned === [] ) {
      $this->registryUnreadable[ $context ] = true;

      return $this->registryKeys[ $context ] = null;
    }

    return $this->registryKeys[ $context ] = $scanned;
  }


  private function withUnlistedRows( array $keys, int $context ): array {
    $rows = $this->query->loadedRowKeys();
    if ( $rows === [] ) {
      $rows = $this->query->scanRowKeys();
    }
    $unlisted = array_values( array_diff( $this->settingKeys( $rows ), $keys ) );
    if ( $unlisted === [] ) {
      return $keys;
    }
    $this->shortRegistry[ $context ] = $keys;

    return array_merge( $keys, $unlisted );
  }


  /**
   * Row keys as settings: the empty key of a row named exactly the prefix is
   * left out (wpmldev-12321, intake (c)).
   *
   * @param list<string> $rowKeys
   *
   * @return list<string>
   */
  private function settingKeys( array $rowKeys ): array {
    return array_values(
      array_filter(
        $rowKeys,
        function ( $key ) {
          return $key !== '';
        }
      )
    );
  }


  private function assemble( array $keys, $storedValue = null, ?int $servedContext = null ): array {
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
      } elseif ( array_key_exists( $key, $this->lostKeyDefaults ) && $this->storedRowServed( $key, $state ) ) {
        continue;
      } elseif ( array_key_exists( $key, $this->lostKeyDefaults ) ) {
        $state[ $key ] = $this->lostKeyDefaults[ $key ];
        $this->writeRow( $key, $state[ $key ] );
        $this->logRestoredToDefault( $key, $state[ $key ] );
      } else {
        $leftMissing[] = $key;
        if ( $servedContext !== null ) {
          $this->unreadableKeys[ $servedContext ][ $key ] = true;
        }
      }
    }

    if ( $copy !== null ) {
      $this->logLeftMissingOnce( $leftMissing );
    }

    return $state;
  }


  private function storedRowServed( string $key, array &$state ): bool {
    $row = $this->query->readRawRow( $key );
    if ( ! is_array( $row ) || ! $this->envelope->isValidRow( $row ) ) {
      return false;
    }
    $state[ $key ] = $this->envelope->value( $row );

    return true;
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


  private function sameKeySet( array $a, array $b ): bool {
    sort( $a );
    sort( $b );

    return $a === $b;
  }


}
