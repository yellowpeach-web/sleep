<?php

namespace WPML\Infrastructure\WordPress\Port\Appearance;

use WPML\Core\Port\Appearance\AppearanceSurfacesInterface;

class AppearanceSurfaces implements AppearanceSurfacesInterface {

  public function surfaces(): array {
    $answer = apply_filters( 'wpml_appearance_surfaces', [] );
    if ( ! is_array( $answer ) ) {
      return [];
    }

    $surfaces = [];
    foreach ( $answer as $type => $row ) {
      if ( ! is_string( $type ) || ! self::isKnownType( $type ) || ! is_array( $row ) || ! isset( $row['enabled'] ) || ! is_bool( $row['enabled'] ) ) {
        continue;
      }

      $label    = isset( $row['label'] ) && is_string( $row['label'] ) && '' !== $row['label'] ? $row['label'] : $type;
      $notShown = $row['not_shown'] ?? null;

      $surfaces[ $type ] = [
        'label'      => $label,
        'enabled'    => $row['enabled'],
        'switchable' => ! ( isset( $row['switchable'] ) && false === $row['switchable'] ),
        'not_shown'  => is_int( $notShown ) || ( is_string( $notShown ) && ctype_digit( $notShown ) ) ? (int) $notShown : null,
      ];
    }

    return $surfaces;
  }


  public function setSurfaces( array $switches ): void {
    do_action( 'wpml_appearance_set_surfaces', $switches );
  }


  private static function isKnownType( string $type ): bool {
    return 'string' === $type || 1 === preg_match( '/^(post|tax|package)_.+$/', $type );
  }


}
