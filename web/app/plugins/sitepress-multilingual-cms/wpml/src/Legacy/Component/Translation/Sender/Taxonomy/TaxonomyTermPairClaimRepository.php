<?php

namespace WPML\Legacy\Component\Translation\Sender\Taxonomy;

use WPML\Core\Component\Translation\Application\Service\Taxonomy\TaxonomyTermPairClaimRepositoryInterface;

final class TaxonomyTermPairClaimRepository implements TaxonomyTermPairClaimRepositoryInterface {

  const LEASE_SECONDS = 180;

  const CHUNK = 200;

  private $wpdb;


  public function __construct( ?\wpdb $wpdb = null ) {
    $this->wpdb = $wpdb ?: $GLOBALS['wpdb'];
  }


  public function claim( int $termTaxonomyId, string $targetLanguage, string $owner ): bool {
    return [] !== $this->claimMany( [ [ $termTaxonomyId, $targetLanguage ] ], $owner );
  }


  public function release( int $termTaxonomyId, string $targetLanguage, string $owner ): bool {
    return $this->releaseMany( [ [ $termTaxonomyId, $targetLanguage ] ], $owner ) > 0;
  }


  public function claimMany( array $pairs, string $owner ): array {
    $owner   = $this->ownerValue( $owner );
    $claimed = [];

    foreach ( array_chunk( $this->namesOf( $pairs ), self::CHUNK, true ) as $chunk ) {
      $args   = [];
      foreach ( array_keys( $chunk ) as $name ) {
        array_push( $args, $name, $owner );
      }
      array_push( $args, $owner, $owner );
      $args = $this->sqlHexLiterals( $args );

      $written = $this->wpdb->query(
        vsprintf(
          'INSERT INTO '
          . implode( '', (array) esc_sql( $this->optionsTable() ) )
          . ' ( option_name, option_value, autoload ) VALUES '
          . implode(
            ', ',
            array_fill(
              0,
              count( $chunk ),
              '( %s, CONCAT( %s, CHAR(124), UNIX_TIMESTAMP() + 180 ), 0x6E6F )'
            )
          ) . '
         ON DUPLICATE KEY UPDATE option_value = IF(
           SUBSTRING_INDEX( option_value, 0x7C, 1 ) = %s
             OR CAST( SUBSTRING_INDEX( option_value, 0x7C, -1 ) AS UNSIGNED ) <= UNIX_TIMESTAMP(),
           CONCAT( %s, CHAR(124), UNIX_TIMESTAMP() + 180 ),
           option_value
         )',
          (array) esc_sql( $args )
        )
      );
      if ( false === $written ) {
        continue;
      }

      foreach ( $this->heldBy( array_keys( $chunk ), $owner ) as $name ) {
        $claimed[ $chunk[ $name ] ] = true;
      }
    }

    return $claimed;
  }


  public function releaseMany( array $pairs, string $owner ): int {
    $owner    = $this->ownerValue( $owner );
    $released = 0;

    foreach ( array_chunk( array_keys( $this->namesOf( $pairs ) ), self::CHUNK ) as $names ) {
      $args    = $this->sqlHexLiterals( array_merge( $names, [ $this->wpdb->esc_like( $owner . '|' ) . '%' ] ) );
      $deleted = $this->wpdb->query(
        vsprintf(
          'DELETE FROM '
          . implode( '', (array) esc_sql( $this->optionsTable() ) )
          . ' WHERE option_name IN ( '
          . implode( ', ', array_fill( 0, count( $names ), '%s' ) )
          . ' ) AND option_value LIKE %s',
          (array) esc_sql( $args )
        )
      );
      $released += is_int( $deleted ) ? $deleted : 0;
    }

    return $released;
  }


  private function heldBy( array $names, string $owner ): array {
    $args = $this->sqlHexLiterals( array_merge( $names, [ $this->wpdb->esc_like( $owner . '|' ) . '%' ] ) );

    return array_map(
      'strval',
      $this->wpdb->get_col(
        vsprintf(
          'SELECT option_name FROM '
          . implode( '', (array) esc_sql( $this->optionsTable() ) )
          . ' WHERE option_name IN ( '
          . implode( ', ', array_fill( 0, count( $names ), '%s' ) )
          . ' ) AND option_value LIKE %s',
          (array) esc_sql( $args )
        )
      )
    );
  }


  private function namesOf( array $pairs ): array {
    $names = [];
    foreach ( $pairs as $pair ) {
      $key                        = $pair[0] . '|' . $pair[1];
      $names[ $this->name( $key ) ] = $key;
    }
    ksort( $names );

    return $names;
  }


  private function name( string $pairKey ): string {
    return 'wpml.taxonomy_term_pair_' . sha1( $pairKey ) . '.claim';
  }


  private function ownerValue( string $owner ): string {
    return str_replace( '|', '-', $owner );
  }


  private function optionsTable(): string {
    $table = $this->wpdb->options;
    if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
      throw new \InvalidArgumentException( 'The WordPress options table name is not valid.' );
    }

    return $table;
  }


  private function sqlHexLiterals( array $values ): array {
    return array_map(
      function ( $value ): string {
        return '0x' . bin2hex( (string) $value );
      },
      $values
    );
  }

}
