<?php

namespace WPML\Infrastructure\WordPress\Component\SettingsStorage;

use WPML\Core\Component\SettingsStorage\Application\Repository\StorageQueryInterface;
use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;
use WPML\Core\Port\Persistence\QueryHandlerInterface;
use WPML\Core\Port\Persistence\QueryPrepareInterface;

class StorageQuery implements StorageQueryInterface {

  private $queryHandler;

  private $queryPrepare;


  public function __construct( QueryHandlerInterface $queryHandler, QueryPrepareInterface $queryPrepare ) {
    $this->queryHandler = $queryHandler;
    $this->queryPrepare = $queryPrepare;
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
      $keys[] = (string) substr( $name, strlen( self::ROW_PREFIX ) );
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

    return $raw === null ? false : maybe_unserialize( $raw );
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


}
