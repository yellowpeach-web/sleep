<?php

namespace WPML\Core\Component\Cdt\Domain;

class DateTimeFormat {


  public static function fromUnix( ?int $timestamp ): ?string {
    if ( $timestamp === null || $timestamp <= 0 ) {
      return null;
    }

    return gmdate( 'Y-m-d\TH:i:s\Z', $timestamp );
  }


}
