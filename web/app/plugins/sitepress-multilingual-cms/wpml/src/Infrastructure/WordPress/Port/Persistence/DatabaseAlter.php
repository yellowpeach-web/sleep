<?php

namespace WPML\Infrastructure\WordPress\Port\Persistence;

use WPML\Core\Port\Persistence\DatabaseAlterInterface;
use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;
use function WPML\PHP\Logger\error as logError;
use WPML\Core\Port\Persistence\QueryPrepareInterface;
use WPML\PHP\Exception\InvalidArgumentException;


class DatabaseAlter implements DatabaseAlterInterface {

  private $wpdb;

  private $queryPrepare;


  public function __construct( $wpdb, QueryPrepareInterface $queryPrepare ) {
    $this->wpdb         = $wpdb;
    $this->queryPrepare = $queryPrepare;
  }


  public function addIndex( string $table, $fields, ?string $name = null ) {
    if ( empty( $fields ) ) {
      throw new InvalidArgumentException( 'No fields provided for index creation.' );
    }

    $fields = ! is_array( $fields ) ? [ $fields ] : $fields;
    foreach ( $fields as &$field ) {
      if ( empty( $field ) || ! is_string( $field ) ) {
        throw new InvalidArgumentException( 'Field names must be a non-empty string.' );
      }
      $field = $this->identifier( $field );
    }

    $name = $name ? $this->identifier( $name ) : $fields[0];

    $table      = $this->identifier( $this->wpdb->prefix . $table );
    $fieldsList = implode( '`, `', $fields );
    $wpdb       = $this->wpdb;

    $indexExists = $wpdb->get_results(
      $wpdb->prepare(
        'SHOW INDEX FROM `' . esc_sql( $table ) . '` WHERE Key_name = %s',
        $name
      )
    );

    if ( $indexExists ) {
      return true;
    }

    $wpdb->query(
      'ALTER TABLE `' . esc_sql( $table ) . '` ADD INDEX `' . esc_sql( $name ) . '` ( `' . esc_sql( $fieldsList ) . '` )'
    );

    if ( $this->wpdb->last_error ) {
      logError( __METHOD__ . ' failed: ' . $this->wpdb->last_error );

      throw new DatabaseErrorException( 'Database query failed.' );
    }

    return true;
  }


  public function addColumn( string $table, string $column, $type, $default = null ) {
    $column = $this->identifier( $column );

    if ( DatabaseAlterInterface::FIELD_TYPE_INT11_UNSIGNED !== $type ) {
      throw new InvalidArgumentException( 'Unsupported column type.' );
    }

    $table = $this->identifier( $this->wpdb->prefix . $table );
    $wpdb  = $this->wpdb;

    $fieldExists = $wpdb->get_results(
      $wpdb->prepare(
        'SHOW COLUMNS FROM `' . esc_sql( $table ) . '` LIKE %s',
        $column
      )
    );

    if ( $fieldExists ) {
      return true;
    }

    if ( null === $default ) {
      $wpdb->query(
        'ALTER TABLE `' . esc_sql( $table ) . '` ADD `' . esc_sql( $column ) . '` INT(11) UNSIGNED NULL'
      );
    } else {
      $wpdb->query(
        $wpdb->prepare(
          'ALTER TABLE `' . esc_sql( $table ) . '` ADD `' . esc_sql( $column ) . '` INT(11) UNSIGNED DEFAULT %s',
          $default
        )
      );
    }

    if ( $this->wpdb->last_error ) {
      logError( __METHOD__ . ' failed: ' . $this->wpdb->last_error );

      throw new DatabaseErrorException( 'Database query failed.' );
    }

    return true;
  }


  public function dropColumn( string $table, string $column ) {
    if ( empty( $table ) || empty( $column ) ) {
      throw new InvalidArgumentException( 'Table and column names must be non-empty strings.' );
    }

    $table  = $this->identifier( $this->wpdb->prefix . $table );
    $column = $this->identifier( $column );
    $wpdb   = $this->wpdb;

    $columnExists = $wpdb->get_results(
      $wpdb->prepare(
        'SHOW COLUMNS FROM `' . esc_sql( $table ) . '` LIKE %s',
        $column
      )
    );

    if ( ! $columnExists ) {
      return true;
    }

    $wpdb->query(
      'ALTER TABLE `' . esc_sql( $table ) . '` DROP COLUMN `' . esc_sql( $column ) . '`'
    );

    if ( $this->wpdb->last_error ) {
      logError( __METHOD__ . ' failed: ' . $this->wpdb->last_error );

      throw new DatabaseErrorException( 'Database query failed.' );
    }

    return true;
  }


  public function truncateColumn( string $table, string $column ) {
    if ( empty( $table ) || empty( $column ) ) {
      throw new InvalidArgumentException( 'Table and column names must be non-empty strings.' );
    }

    $table  = $this->identifier( $this->wpdb->prefix . $table );
    $column = $this->identifier( $column );

    $this->wpdb->query(
      'UPDATE `' . esc_sql( $table ) . '` SET `' . esc_sql( $column ) . '` = NULL'
    );

    if ( $this->wpdb->last_error ) {
      logError( __METHOD__ . ' failed: ' . $this->wpdb->last_error );

      throw new DatabaseErrorException( 'Database query failed.' );
    }

    return true;
  }


  private function identifier( string $identifier ): string {
    if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $identifier ) ) {
      throw new InvalidArgumentException( 'Database identifiers may contain only letters, numbers, and underscores.' );
    }

    return $this->queryPrepare->escString( $identifier );
  }


}
