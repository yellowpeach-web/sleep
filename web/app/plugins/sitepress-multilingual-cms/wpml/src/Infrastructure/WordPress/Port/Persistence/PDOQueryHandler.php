<?php

namespace WPML\Infrastructure\WordPress\Port\Persistence;

use PDO;
use PDOException;
use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;
use function WPML\PHP\Logger\error as logError;
use WPML\Core\Port\Persistence\QueryHandlerInterface;
use WPML\Core\Port\Persistence\ResultCollection;
use WPML\Core\Port\Persistence\ResultCollectionInterface;
use tad\FunctionMocker\ReturnValue;

class PDOQueryHandler implements QueryHandlerInterface {

  private $pdo;


  public function __construct( PDO $pdo ) {
    $this->pdo = $pdo;
  }


  public function query( string $query ): ResultCollectionInterface {
    ReadStatement::assert( $query );

    try {
      $stmt = $this->pdo->query( $query );
      if ( ! $stmt ) {
        throw new DatabaseErrorException( 'Query failed' );
      }
      $data = $stmt->fetchAll( PDO::FETCH_ASSOC );

      return new ResultCollection( $data ?: [] );
    } catch ( PDOException $e ) {
      logError( __METHOD__ . ' failed: ' . $e->getMessage() );

      throw new DatabaseErrorException( 'Database query failed.' );
    }
  }


  public function queryOne( string $query ) {
    ReadStatement::assert( $query );

    try {
      $stmt = $this->pdo->query( $query );
      if ( ! $stmt ) {
        throw new DatabaseErrorException( 'Query failed' );
      }

      $data = $stmt->fetch( PDO::FETCH_ASSOC );

      return $data ?: null;
    } catch ( PDOException $e ) {
      logError( __METHOD__ . ' failed: ' . $e->getMessage() );

      throw new DatabaseErrorException( 'Database query failed.' );
    }
  }


  public function querySingle( string $query ) {
    ReadStatement::assert( $query );

    try {
      $stmt = $this->pdo->query( $query );
      if ( ! $stmt ) {
        throw new DatabaseErrorException( 'Query failed' );
      }
      $value = $stmt->fetchColumn();

      return $value !== false ? $value : null;
    } catch ( PDOException $e ) {
      logError( __METHOD__ . ' failed: ' . $e->getMessage() );

      throw new DatabaseErrorException( 'Database query failed.' );
    }
  }


  public function queryColumn( string $query ): array {
    ReadStatement::assert( $query );

    try {
      $stmt = $this->pdo->query( $query );
      if ( ! $stmt ) {
        throw new DatabaseErrorException( 'Query failed' );
      }
      $result = $stmt->fetchAll( PDO::FETCH_COLUMN );

      return $result;
    } catch ( PDOException $e ) {
      logError( __METHOD__ . ' failed: ' . $e->getMessage() );

      throw new DatabaseErrorException( 'Database query failed.' );
    }
  }


}
