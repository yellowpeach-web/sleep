<?php

namespace WPML\Core\Component\CustomFieldPreferences\Domain;

final class MapDiff {

  private $upserts;

  private $deletions;


  private function __construct( array $upserts, array $deletions ) {
    $this->upserts   = $upserts;
    $this->deletions = $deletions;
  }


  public static function between( array $incoming, array $current ): self {
    $incoming = self::normalized( $incoming );
    $current  = self::normalized( $current );

    $upserts = [];
    foreach ( $incoming as $name => $mode ) {
      if ( ! isset( $current[ $name ] ) || $current[ $name ] !== $mode ) {
        $upserts[ $name ] = $mode;
      }
    }

    return new self( $upserts, array_keys( array_diff_key( $current, $incoming ) ) );
  }


  public function upserts(): array {
    return $this->upserts;
  }


  public function deletions(): array {
    return $this->deletions;
  }


  public function isEmpty(): bool {
    return $this->upserts === [] && $this->deletions === [];
  }


  private static function normalized( array $map ): array {
    $normalized = [];
    foreach ( $map as $name => $mode ) {
      if ( (string) $name === '' || ! is_scalar( $mode ) ) {
        continue;
      }
      $normalized[ $name ] = (int) $mode;
    }

    return $normalized;
  }


}
