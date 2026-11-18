<?php

namespace WPML\Infrastructure\WordPress\Port\Appearance;

use WPML\Core\Port\Appearance\AppearanceReaderInterface;

class AppearanceReader implements AppearanceReaderInterface {

  const MODES_ON = [ 'admin', 'visitors' ];


  public function has( string $type, int $id ): ?bool {
    $answer = apply_filters( 'wpml_appearance_has', null, $type, $id );

    return is_bool( $answer ) ? $answer : null;
  }


  public function filterIds( string $type, array $ids ): ?array {
    $answer = apply_filters( 'wpml_appearance_filter_ids', null, $type, $ids );
    if ( ! is_array( $answer ) ) {
      return null;
    }

    return array_values( array_map( 'intval', $answer ) );
  }


  public function countMissing( string $type ): ?int {
    $answer = apply_filters( 'wpml_appearance_count_missing', null, $type );

    return is_int( $answer ) || ( is_string( $answer ) && ctype_digit( $answer ) ) ? (int) $answer : null;
  }


  public function heldTypes(): array {
    $declared = apply_filters( 'wpml_appearance_part_of_types', [] );
    if ( ! is_array( $declared ) ) {
      return [];
    }

    $held = [];
    foreach ( $declared as $type ) {
      if ( self::isKnownType( $type ) && ! in_array( $type, $held, true ) && self::isOnFor( $type ) ) {
        $held[] = $type;
      }
    }

    return $held;
  }


  private static function isOnFor( string $type ): bool {
    $mode = apply_filters( 'wpml_appearance_detection_mode', 'off', $type );

    return in_array( $mode, self::MODES_ON, true );
  }


  public function existsSql( string $typeExpr, string $idExpr ): string {
    $answer = apply_filters( 'wpml_appearance_exists_sql', '', $typeExpr, $idExpr );

    return is_string( $answer ) ? $answer : '';
  }


  private static function isKnownType( $type ): bool {
    return is_string( $type )
      && ( 'string' === $type || 1 === preg_match( '/^(post|tax|package)_.+$/', $type ) );
  }


}
