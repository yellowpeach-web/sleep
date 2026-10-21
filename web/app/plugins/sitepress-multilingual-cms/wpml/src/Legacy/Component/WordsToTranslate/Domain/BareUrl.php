<?php

namespace WPML\Legacy\Component\WordsToTranslate\Domain;

class BareUrl {

  private const PATTERN = '/^(https?):\/\/[^\s\/$.?#].[^\s]*$/i';

  public static function matches( string $value ): bool {
    return (bool) preg_match( self::PATTERN, $value );
  }

}
