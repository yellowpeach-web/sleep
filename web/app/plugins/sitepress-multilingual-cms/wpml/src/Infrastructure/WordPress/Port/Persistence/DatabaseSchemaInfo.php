<?php

namespace WPML\Infrastructure\WordPress\Port\Persistence;

use WPML\Core\Port\Persistence\DatabaseSchemaInfoInterface;
use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;
use function WPML\PHP\Logger\error as logError;
use WPML\Core\Port\Persistence\QueryPrepareInterface;
use WPML\PHP\Exception\InvalidArgumentException;

class DatabaseSchemaInfo implements DatabaseSchemaInfoInterface {

  private $wpdb;

  private $queryPrepare;


  public function __construct( $wpdb, QueryPrepareInterface $queryPrepare ) {
    $this->wpdb         = $wpdb;
    $this->queryPrepare = $queryPrepare;
  }


  public function doesColumnExist( string $table, string $column ): bool {
    if ( empty( $table ) || empty( $column ) ) {
      throw new InvalidArgumentException( 'Table and column names must be non-empty strings.' );
    }

    $wpdb   = $this->wpdb;
    $table  = $this->identifier( $wpdb->prefix . $table );
    $column = $this->identifier( $column );

    $result = $wpdb->get_results(
      $wpdb->prepare(
        'SHOW COLUMNS FROM `' . esc_sql( $table ) . '` LIKE %s',
        $column
      )
    );

    if ( $this->wpdb->last_error ) {
      logError( __METHOD__ . ' failed: ' . $this->wpdb->last_error );

      throw new DatabaseErrorException( 'Database query failed.' );
    }

    return ! empty( $result );
  }


  public function doesTableExist( string $table ): bool {
    if ( empty( $table ) ) {
      throw new InvalidArgumentException( 'Table name must be a non-empty string.' );
    }

    $wpdb  = $this->wpdb;
    $table = $this->identifier( $wpdb->prefix . $table );

    $result = $wpdb->get_results(
      $wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
    );

    if ( $this->wpdb->last_error ) {
      logError( __METHOD__ . ' failed: ' . $this->wpdb->last_error );

      throw new DatabaseErrorException( 'Database query failed.' );
    }

    return ! empty( $result );
  }


  private function identifier( string $identifier ): string {
    if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $identifier ) ) {
      throw new InvalidArgumentException( 'Database identifiers may contain only letters, numbers, and underscores.' );
    }

    return $this->queryPrepare->escString( $identifier );
  }


}
