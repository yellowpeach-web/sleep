<?php

namespace WPML\Core\SharedKernel\Component\Post\Domain;

class TranslateEverythingScope {

  const TYPES_NOT_OFFERED = [ 'attachment', 'product_variation' ];


  public static function offeredOnly( array $types ): array {
    return array_values( array_diff( $types, self::TYPES_NOT_OFFERED ) );
  }


  public static function isOffered( string $type ): bool {
    return ! in_array( $type, self::TYPES_NOT_OFFERED, true );
  }


}
