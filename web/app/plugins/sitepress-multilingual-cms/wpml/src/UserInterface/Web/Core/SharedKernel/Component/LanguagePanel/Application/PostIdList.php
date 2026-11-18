<?php

namespace WPML\UserInterface\Web\Core\SharedKernel\Component\LanguagePanel\Application;

final class PostIdList {

  const MAX_IDS = 100;


  public static function parse( $value ): array {
    if ( is_string( $value ) ) {
      $value = explode( ',', $value );
    }
    if ( ! is_array( $value ) ) {
      return [];
    }

    $ids = [];
    foreach ( $value as $one ) {
      if ( is_numeric( $one ) && (int) $one > 0 ) {
        $ids[] = (int) $one;
      }
    }

    return array_values( array_unique( $ids ) );
  }


  public static function exceedsTheCap( $value ): bool {
    if ( is_string( $value ) ) {
      $value = explode( ',', $value );
    }

    return is_array( $value ) && count( $value ) > self::MAX_IDS;
  }


}
