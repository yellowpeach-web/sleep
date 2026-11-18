<?php

namespace WPML\Infrastructure\WordPress\Component\LanguagePanel\Filter;

use WPML\Core\Component\Post\Application\LanguagePanel\PanelDataFilterInterface;

class PanelDataFilter implements PanelDataFilterInterface {

  const HOOK = 'wpml_post_language_panel_data';

  const PLACEMENT_PANEL  = 'panel';
  const PLACEMENT_EDITOR = 'editor';


  public function apply( array $data, int $postId, array $context = [] ): array {
    $filtered = apply_filters( self::HOOK, $data, $postId, $context );

    if ( ! is_array( $filtered ) ) {
      return $data;
    }

    foreach ( $data as $key => $value ) {
      if ( ! array_key_exists( $key, $filtered ) ) {
        $filtered[ $key ] = $value;
        continue;
      }
      if ( null === $value ) {
        if ( null !== $filtered[ $key ] && ! is_array( $filtered[ $key ] ) ) {
          $filtered[ $key ] = null;
        }
        continue;
      }
      if ( gettype( $filtered[ $key ] ) !== gettype( $value ) ) {
        $filtered[ $key ] = $value;
        continue;
      }
      if ( is_array( $value ) && is_array( $filtered[ $key ] ) && self::isList( $value ) && ! self::isList( $filtered[ $key ] ) ) {
        $filtered[ $key ] = $value;
      }
    }

    $filtered['notices'] = self::notices( $filtered['notices'] ?? [] );

    return $filtered;
  }


  private static function isList( array $value ): bool {
    return [] === $value || array_keys( $value ) === range( 0, count( $value ) - 1 );
  }


  private static function notices( $notices ): array {
    $clean = [];

    foreach ( is_array( $notices ) ? $notices : [] as $notice ) {
      if ( ! is_array( $notice ) || empty( $notice['id'] ) || ! is_string( $notice['id'] ) || ! isset( $notice['text'] ) || ! is_string( $notice['text'] ) ) {
        continue;
      }

      $type = isset( $notice['type'] ) && in_array( $notice['type'], [ 'info', 'warning', 'error', 'success' ], true )
        ? $notice['type']
        : 'info';

      $actions = [];
      foreach ( is_array( $notice['actions'] ?? null ) ? $notice['actions'] : [] as $action ) {
        if ( is_array( $action ) && isset( $action['label'], $action['url'] ) && is_string( $action['label'] ) && is_string( $action['url'] ) ) {
          $actions[] = [ 'label' => $action['label'], 'url' => $action['url'] ];
        }
      }

      $clean[] = [
        'id'        => $notice['id'],
        'type'      => $type,
        'text'      => $notice['text'],
        'actions'   => $actions,
        'placement' => self::PLACEMENT_EDITOR === ( $notice['placement'] ?? null ) ? self::PLACEMENT_EDITOR : self::PLACEMENT_PANEL,
      ];
    }

    return $clean;
  }


}
