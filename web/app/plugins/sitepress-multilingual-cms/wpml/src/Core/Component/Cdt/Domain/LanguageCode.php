<?php

namespace WPML\Core\Component\Cdt\Domain;

class LanguageCode {

  const PATTERN    = '/^[a-z]{2,3}(-[a-zA-Z]{2,8})?$/';
  const MAX_LENGTH = 12;

  const MAX_LANGUAGES = 256;


  public static function isValid( $code ): bool {
    return is_string( $code ) && strlen( $code ) <= self::MAX_LENGTH && preg_match( self::PATTERN, $code ) === 1;
  }


}
