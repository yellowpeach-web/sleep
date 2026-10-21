<?php

namespace WPML\Infrastructure\WordPress\Port\Persistence;

use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;
use function WPML\PHP\Logger\error as logError;


class DatabaseWrite implements \WPML\Core\Port\Persistence\DatabaseWriteInterface {

  private $wpdb;


  public function __construct( $wpdb ) {
    $this->wpdb = $wpdb;
  }


  public function insert( string $table, array $entityData ): int {
    $table = $this->wpdb->prefix . $table;

    $this->wpdb->insert( $table, $entityData );

    if ( $this->wpdb->last_error ) {
      logError( __METHOD__ . ' failed: ' . $this->wpdb->last_error );

      throw new DatabaseErrorException( 'Database query failed.' );
    }

    return $this->wpdb->insert_id;
  }


  public function insertMany( string $table, array $entitiesData ) {
    if ( ! $entitiesData ) {
      return;
    }

    $table = $this->identifier( $this->wpdb->prefix . $table );

    $fieldNames = $this->identifiers( array_keys( $entitiesData[0] ) );
    $fields     = implode( ', ', $fieldNames );
    $args       = $this->values( $entitiesData );

    $this->wpdb->query(
      vsprintf(
        'INSERT IGNORE INTO ' . esc_sql( $table ) . ' ( ' . esc_sql( $fields ) . ' ) VALUES '
        . implode(
          ', ',
          array_fill(
            0,
            count( $entitiesData ),
            '(' . implode( ', ', array_fill( 0, count( $fieldNames ), "'%s'" ) ) . ')'
          )
        ),
        esc_sql( $args )
      )
    );

    if ( $this->wpdb->last_error ) {
      logError( __METHOD__ . ' failed: ' . $this->wpdb->last_error );

      throw new DatabaseErrorException( 'Database query failed.' );
    }
  }


  private function values( array $entitiesData ): array {
    $args    = [];
    $columns = array_keys( (array) reset( $entitiesData ) );

    foreach ( $entitiesData as $entityData ) {
      if ( array_keys( $entityData ) !== $columns ) {
        throw new \InvalidArgumentException( 'Every bulk row must carry the same columns in the same order.' );
      }

      foreach ( array_values( $entityData ) as $value ) {
        if ( ! is_scalar( $value ) && null !== $value ) {
          throw new \InvalidArgumentException( 'Bulk database values must be scalar or null.' );
        }

        $args[] = (string) ( $value ?? '' );
      }
    }

    return $args;
  }


  public function update( string $table, array $entityData, array $whereData ): int {
    $this->wpdb->update( $this->wpdb->prefix . $table, $entityData, $whereData );

    if ( $this->wpdb->last_error ) {
      logError( __METHOD__ . ' failed: ' . $this->wpdb->last_error );

      throw new DatabaseErrorException( 'Database query failed.' );
    }

    return $this->wpdb->rows_affected;
  }


  public function delete( string $table, array $whereData ): int {
    $this->wpdb->delete( $this->wpdb->prefix . $table, $whereData );

    if ( $this->wpdb->last_error ) {
      logError( __METHOD__ . ' failed: ' . $this->wpdb->last_error );

      throw new DatabaseErrorException( 'Database query failed.' );
    }

    return $this->wpdb->rows_affected;
  }


  public function upsertMany( string $table, array $entitiesData, array $updateColumns ) {
    if ( ! $entitiesData ) {
      return;
    }
    $fieldNames = $this->identifiers( array_keys( $entitiesData[0] ) );
    $table      = $this->identifier( $this->wpdb->prefix . $table );
    $fields     = implode( ', ', $fieldNames );
    $updateColumns = $this->identifiers( $updateColumns );

    if ( array_diff( $updateColumns, $fieldNames ) ) {
      throw new \InvalidArgumentException( 'Updated columns must be present in every upsert row.' );
    }

    $args = $this->values( $entitiesData );

    $updates = implode(
      ', ',
      array_map(
        function ( $column ) {
          return $column . ' = VALUES(' . $column . ')';
        },
        $updateColumns
      )
    );

    $this->wpdb->query(
      vsprintf(
        'INSERT INTO ' . esc_sql( $table ) . ' ( ' . esc_sql( $fields ) . ' ) VALUES '
        . implode(
          ', ',
          array_fill(
            0,
            count( $entitiesData ),
            '(' . implode( ', ', array_fill( 0, count( $fieldNames ), "'%s'" ) ) . ')'
          )
        )
        . ' ON DUPLICATE KEY UPDATE ' . esc_sql( $updates ),
        esc_sql( $args )
      )
    );

    if ( $this->wpdb->last_error ) {
      logError( __METHOD__ . ' failed: ' . $this->wpdb->last_error );

      throw new DatabaseErrorException( 'Database query failed.' );
    }
  }


  public function execute( string $sql ): int {
    $this->wpdb->query( $sql );

    if ( $this->wpdb->last_error ) {
      logError( __METHOD__ . ' failed: ' . $this->wpdb->last_error );

      throw new DatabaseErrorException( 'Database query failed.' );
    }

    return $this->wpdb->rows_affected;
  }


  private function identifier( string $identifier ): string {
    if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $identifier ) ) {
      throw new \InvalidArgumentException( 'Database identifiers may contain only letters, numbers, and underscores.' );
    }

    return $identifier;
  }


  private function identifiers( array $identifiers ): array {
    return array_map( [ $this, 'identifier' ], $identifiers );
  }


}
