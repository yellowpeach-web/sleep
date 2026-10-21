<?php

namespace WPML\Infrastructure\WordPress\Component\CustomFieldPreferences\Repository;

use WPML\Core\Component\CustomFieldPreferences\Application\Repository\PreferenceRepositoryInterface;
use WPML\Core\Component\CustomFieldPreferences\Domain\ElementType;
use WPML\Core\Component\CustomFieldPreferences\Domain\StorableKeys;
use WPML\Core\Component\CustomFieldPreferences\Domain\StorableModes;
use WPML\Core\Port\Persistence\DatabaseWriteInterface;
use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;
use WPML\Core\Port\Persistence\QueryHandlerInterface;
use WPML\Core\Port\Persistence\QueryPrepareInterface;
use WPML\Upgrade\CreateTable;

use function WPML\PHP\Logger\error;

class PreferenceRepository implements PreferenceRepositoryInterface {

  const TABLE      = 'icl_meta_settings';
  const CHUNK_SIZE = 500;

  private $queryHandler;

  private $queryPrepare;

  private $databaseWrite;

  public function __construct(
    QueryHandlerInterface $queryHandler,
    QueryPrepareInterface $queryPrepare,
    DatabaseWriteInterface $databaseWrite
  ) {
    $this->queryHandler  = $queryHandler;
    $this->queryPrepare  = $queryPrepare;
    $this->databaseWrite = $databaseWrite;
  }


  private function table(): string {
    return $this->queryPrepare->prefix() . self::TABLE;
  }


  public function getMap( string $type ): array {
    try {
      $rows = $this->queryHandler->query(
        $this->queryPrepare->prepare(
          'SELECT name, mode FROM ' . $this->table() . ' WHERE element_type = %s ORDER BY id',
          $type
        )
      )->getResults();
    } catch ( DatabaseErrorException $e ) {
      error( 'WPML meta-settings map read failed: ' . $e->getMessage() );
      return [];
    }

    $map = [];
    foreach ( $rows as $row ) {
      $map[ $row['name'] ] = (int) $row['mode'];
    }
    return StorableModes::quarantine( $map, $type );
  }


  public function getMaps(): array {
    try {
      $rows = $this->queryHandler->query(
        'SELECT element_type, name, mode FROM ' . $this->table() . ' ORDER BY id'
      )->getResults();
    } catch ( DatabaseErrorException $e ) {
      error( 'WPML meta-settings maps read failed: ' . $e->getMessage() );
      return [];
    }

    $maps = [];
    foreach ( ElementType::all() as $type ) {
      $maps[ $type ] = [];
    }
    foreach ( $rows as $row ) {
      if ( isset( $maps[ $row['element_type'] ] ) ) {
        $maps[ $row['element_type'] ][ $row['name'] ] = (int) $row['mode'];
      }
    }
    foreach ( $maps as $type => $map ) {
      $maps[ $type ] = StorableModes::quarantine( $map, $type );
    }
    return $maps;
  }


  public function getMode( string $type, string $name ) {
    try {
      $mode = $this->queryHandler->querySingle(
        $this->queryPrepare->prepare(
          'SELECT mode FROM ' . $this->table() . ' WHERE element_type = %s AND name = %s',
          $type,
          $name
        )
      );
    } catch ( DatabaseErrorException $e ) {
      error( 'WPML meta-settings mode read failed: ' . $e->getMessage() );
      return null;
    }

    if ( $mode === null ) {
      return null;
    }

    $kept = StorableModes::quarantine( [ $name => (int) $mode ], $type );

    return $kept ? (int) $mode : null;
  }


  public function getModes( string $type, array $names ): array {
    $names = array_values( array_unique( array_map( 'strval', $names ) ) );
    $modes = array_fill_keys( $names, null );
    if ( ! $names ) {
      return $modes;
    }

    $found = [];
    foreach ( array_chunk( $names, self::CHUNK_SIZE ) as $chunk ) {
      $placeholders = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
      try {
        $rows = $this->queryHandler->query(
          $this->queryPrepare->prepare(
            'SELECT name, mode FROM ' . $this->table()
            . ' WHERE element_type = %s AND name IN (' . $placeholders . ')',
            $type,
            ...$chunk
          )
        )->getResults();
      } catch ( DatabaseErrorException $e ) {
        error( 'WPML meta-settings modes read failed: ' . $e->getMessage() );
        continue;
      }

      foreach ( $rows as $row ) {
        $found[ $row['name'] ] = (int) $row['mode'];
      }
    }

    foreach ( StorableModes::quarantine( $found, $type ) as $name => $mode ) {
      $modes[ (string) $name ] = $mode;
    }

    return $modes;
  }


  public function upsertMany( string $type, array $nameToMode ): bool {
    if ( ! $nameToMode ) {
      return true;
    }
    $storable = StorableKeys::filter( $nameToMode );
    StorableKeys::logDropped( 'upsert', $type, StorableKeys::dropped( $nameToMode, $storable ) );
    if ( ! $storable ) {
      return true;
    }

    $ok = true;
    foreach ( array_chunk( $storable, self::CHUNK_SIZE, true ) as $chunk ) {
      $rows = [];
      foreach ( $chunk as $name => $mode ) {
        $rows[] = [
          'element_type' => $type,
          'name'         => (string) $name,
          'name_hash'    => self::nameHash( (string) $name ),
          'mode'         => $mode,
        ];
      }
      try {
        $this->databaseWrite->upsertMany( self::TABLE, $rows, [ 'mode' ] );
      } catch ( DatabaseErrorException | \InvalidArgumentException $e ) {
        error( 'WPML meta-settings upsert failed: ' . $e->getMessage() );
        $ok = false;
      }
    }
    return $ok;
  }


  public function deleteMany( string $type, array $names ): bool {
    if ( ! $names ) {
      return true;
    }

    $ok = true;
    foreach ( array_chunk( $names, self::CHUNK_SIZE ) as $chunk ) {
      $hashes = array_map(
        function ( $name ): string {
          return self::nameHash( (string) $name );
        },
        $chunk
      );
      try {
        $this->databaseWrite->execute(
          $this->queryPrepare->prepare(
            'DELETE FROM ' . $this->table() . ' WHERE element_type = %s AND name_hash IN ('
            . $this->queryPrepare->prepareIn( $hashes, '%s' ) . ')',
            $type
          )
        );
      } catch ( DatabaseErrorException $e ) {
        error( 'WPML meta-settings delete failed: ' . $e->getMessage() );
        $ok = false;
      }
    }
    return $ok;
  }


  public function countByType( string $type ): int {
    try {
      $count = $this->queryHandler->querySingle(
        $this->queryPrepare->prepare(
          'SELECT COUNT(*) FROM ' . $this->table() . ' WHERE element_type = %s',
          $type
        )
      );
      return (int) $count;
    } catch ( DatabaseErrorException $e ) {
      error( 'WPML meta-settings count failed: ' . $e->getMessage() );
      return 0;
    }
  }


  public function resetRuntimeCache() {
  }


  public static function createTable(): bool {
    $wpdb  = $GLOBALS['wpdb'];
    $table = $wpdb->prefix . self::TABLE;

    return CreateTable::create(
      $wpdb,
      $table,
      "CREATE TABLE IF NOT EXISTS `{$table}` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `element_type` ENUM('post','term') NOT NULL DEFAULT 'post',
        `name` VARCHAR(255) BINARY NOT NULL,
        `name_hash` CHAR(40) NOT NULL DEFAULT '',
        `mode` TINYINT UNSIGNED NOT NULL DEFAULT 0,
        UNIQUE KEY `type_name_hash` (`element_type`, `name_hash`),
        KEY `type_name_lookup` (`element_type`, `name`(191))
      )"
    );
  }


  public static function nameHash( string $name ): string {
    return sha1( $name );
  }


  public static function truncateTable(): bool {
    $wpdb  = $GLOBALS['wpdb'];
    $table = $wpdb->prefix . self::TABLE;

    if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
      return true;
    }

    return $wpdb->query( "TRUNCATE TABLE `{$table}`" ) !== false;
  }


}
