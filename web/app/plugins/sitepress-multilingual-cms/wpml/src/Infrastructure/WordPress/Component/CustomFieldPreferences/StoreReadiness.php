<?php

namespace WPML\Infrastructure\WordPress\Component\CustomFieldPreferences;

use WPML\Core\Component\CustomFieldPreferences\Application\Repository\StoreReadinessInterface;
use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;
use WPML\Core\Port\Persistence\QueryHandlerInterface;
use WPML\Core\Port\Persistence\QueryPrepareInterface;
use WPML\Infrastructure\WordPress\Component\CustomFieldPreferences\Repository\PreferenceRepository;

class StoreReadiness implements StoreReadinessInterface {

  const TRANSIENT = 'wpml_meta_settings_store_ready';

  const REQUIRED_COLUMN = 'name_hash';

  private static $ready = [];

  private static $missing = [];

  private $queryHandler;

  private $queryPrepare;


  public function __construct( QueryHandlerInterface $queryHandler, QueryPrepareInterface $queryPrepare ) {
    $this->queryHandler = $queryHandler;
    $this->queryPrepare = $queryPrepare;
  }


  public function isReady(): bool {
    $blog = \get_current_blog_id();

    if ( PreferenceRepository::tableMissingThisRequest() ) {
      return false;
    }

    if ( ! isset( self::$ready[ $blog ] ) ) {
      self::$ready[ $blog ] = \get_transient( self::TRANSIENT ) === '1' || $this->look();
    }

    return self::$ready[ $blog ];
  }


  public function tableIsMissing(): bool {
    if ( ! PreferenceRepository::tableMissingThisRequest() ) {
      return false;
    }

    $blog = \get_current_blog_id();
    if ( ! isset( self::$missing[ $blog ] ) ) {
      self::$missing[ $blog ] = $this->tableIsAbsent();
    }

    return self::$missing[ $blog ];
  }


  private function tableIsAbsent(): bool {
    $table = $this->queryPrepare->prefix() . PreferenceRepository::TABLE;

    try {
      $rows = $this->queryHandler->query(
        $this->queryPrepare->prepare( 'SHOW TABLES LIKE %s', $table )
      )->getResults();
    } catch ( DatabaseErrorException $exception ) {
      unset( $exception );

      return false;
    }

    foreach ( $rows as $row ) {
      if ( in_array( $table, array_values( $row ), true ) ) {
        return false;
      }
    }

    return true;
  }


  private function look(): bool {
    $ready = $this->columnIsThere();

    if ( $ready ) {
      \set_transient( self::TRANSIENT, '1' );
    }

    return $ready;
  }


  private function columnIsThere(): bool {
    $table = $this->queryPrepare->prefix() . PreferenceRepository::TABLE;

    try {
      $rows = $this->queryHandler->query(
        $this->queryPrepare->prepare(
          'SHOW COLUMNS FROM ' . $table . ' LIKE %s',
          self::REQUIRED_COLUMN
        )
      )->getResults();
    } catch ( DatabaseErrorException $exception ) {
      unset( $exception );

      return false;
    }

    return count( $rows ) > 0;
  }


  public static function forget() {
    self::$ready   = [];
    self::$missing = [];
    \delete_transient( self::TRANSIENT );
  }


}
