<?php

namespace WPML\Infrastructure\WordPress\Component\SettingsStorage;

use WPML\Core\Component\SettingsStorage\Application\Repository\StorageQueryInterface;
use WPML\Core\Port\Persistence\DatabaseWriteInterface;
use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;
use WPML\Core\Port\Persistence\QueryHandlerInterface;
use WPML\Core\Port\Persistence\QueryPrepareInterface;

class StorageQuery implements StorageQueryInterface {

  private $queryHandler;

  private $queryPrepare;

  private $databaseWrite;


  public function __construct(
    QueryHandlerInterface $queryHandler,
    QueryPrepareInterface $queryPrepare,
    ?DatabaseWriteInterface $databaseWrite = null
  ) {
    $this->queryHandler  = $queryHandler;
    $this->queryPrepare  = $queryPrepare;
    $this->databaseWrite = $databaseWrite;
  }


  public function scanRowKeys(): array {
    $prepare = $this->queryPrepare;
    $sql     = $prepare->prepare(
      'SELECT option_name FROM ' . $prepare->prefix() . 'options WHERE option_name LIKE %s AND option_name != %s',
      $prepare->escLike( self::ROW_PREFIX ) . '%',
      self::REGISTRY_ROW
    );

    try {
      $names = $this->queryHandler->queryColumn( $sql );
    } catch ( DatabaseErrorException $e ) {
      return [];
    }

    $keys = [];
    foreach ( $names as $name ) {
      if ( strpos( $name, self::ROW_PREFIX ) !== 0 ) {
        return [];
      }
      if ( strlen( $name ) > strlen( self::ROW_PREFIX ) ) {
        $keys[] = (string) substr( $name, strlen( self::ROW_PREFIX ) );
      }
    }

    return $keys;
  }


  public function loadedRowKeys(): array {
    $keys = [];
    foreach ( array_keys( wp_load_alloptions() ) as $name ) {
      $name = (string) $name;
      if ( strpos( $name, self::ROW_PREFIX ) === 0 && $name !== self::REGISTRY_ROW ) {
        $keys[] = (string) substr( $name, strlen( self::ROW_PREFIX ) );
      }
    }

    return $keys;
  }


  public function readRawStoredBlob() {
    $prepare = $this->queryPrepare;
    $sql     = $prepare->prepare(
      'SELECT option_value FROM ' . $prepare->prefix() . 'options WHERE option_name = %s LIMIT 1',
      self::OPTION_NAME
    );

    try {
      $raw = $this->queryHandler->querySingle( $sql );
    } catch ( DatabaseErrorException $e ) {
      return false;
    }

    if ( null === $raw ) {
      return false;
    }

    return @unserialize( trim( $raw ), [ 'allowed_classes' => [ 'stdClass', 'WPML_TP_Service' ] ] );
  }


  public function readRawRow( string $key ) {
    $prepare = $this->queryPrepare;
    $sql     = $prepare->prepare(
      'SELECT option_value FROM ' . $prepare->prefix() . 'options WHERE option_name = %s LIMIT 1',
      self::ROW_PREFIX . $key
    );

    try {
      $raw = $this->queryHandler->querySingle( $sql );
    } catch ( DatabaseErrorException $e ) {
      return false;
    }

    if ( null === $raw ) {
      return false;
    }

    return @unserialize( trim( $raw ), [ 'allowed_classes' => [ 'stdClass', 'WPML_TP_Service' ] ] );
  }


  public function optionRowExists( string $optionName ): bool {
    $notoptions = wp_cache_get( 'notoptions', 'options' );

    return ! ( is_array( $notoptions ) && ! empty( $notoptions[ $optionName ] ) );
  }


  public function registryRowExistsInDatabase( bool $onError = true ): bool {
    $prepare = $this->queryPrepare;
    $sql     = $prepare->prepare(
      'SELECT 1 FROM ' . $prepare->prefix() . 'options WHERE option_name = %s LIMIT 1',
      self::REGISTRY_ROW
    );

    try {
      return $this->queryHandler->querySingle( $sql ) !== null;
    } catch ( DatabaseErrorException $e ) {
      return $onError;
    }
  }


  public function stopAutoloadingStoredBlob() {
    if ( function_exists( 'wp_set_option_autoload' ) ) {
      wp_set_option_autoload( self::OPTION_NAME, false );

      return;
    }

    if ( $this->databaseWrite === null ) {
      return;
    }

    try {
      $updated = $this->databaseWrite->update(
        'options',
        [ 'autoload' => 'no' ],
        [ 'option_name' => self::OPTION_NAME ]
      );
    } catch ( DatabaseErrorException $e ) {
      return;
    }

    if ( $updated > 0 ) {
      wp_cache_delete( 'alloptions', 'options' );
    }
  }


}
