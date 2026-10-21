<?php

namespace WPML\Core\Component\LanguageEditor\Domain;

final class Bcp47 {

  public static function compose( $language, $script = null, $country = null ): string {
      $tag = strtolower( trim( (string) $language ) );
    if ( $tag === '' ) {
        return '';
    }

      $script = strtolower( trim( (string) $script ) );
    if ( $script !== '' ) {
        $tag .= '-' . ucfirst( $script );
    }

      $country = strtoupper( trim( (string) $country ) );
    if ( $country !== '' ) {
        $tag .= '-' . $country;
    }

      return $tag;
  }


  public static function fromLegacyCode( $code ): string {
      $code = strtolower( trim( (string) $code ) );
    if ( $code === '' ) {
        return '';
    }

      $parts = explode( '-', $code );
      $tag   = array_shift( $parts );

    foreach ( $parts as $part ) {
        $tag .= '-' . self::recase( $part );
    }

      return $tag;
  }


  public static function languageSubtag( $tag ): string {
      $tag = strtolower( trim( (string) $tag ) );
    if ( $tag === '' ) {
        return '';
    }

      $parts = explode( '-', $tag );

      return $parts[0];
  }


  public static function scriptSubtag( $tag ): ?string {
      $parts = explode( '-', strtolower( trim( (string) $tag ) ) );
      array_shift( $parts );

    foreach ( $parts as $part ) {
      if ( strlen( $part ) === 4 && ctype_alpha( $part ) ) {
          return $part;
      }
    }

      return null;
  }


  private static function recase( string $part ): string {
    if ( strlen( $part ) === 4 && ctype_alpha( $part ) ) {
        return ucfirst( $part );
    }

    if ( strlen( $part ) === 2 && ctype_alpha( $part ) ) {
        return strtoupper( $part );
    }

    if ( strlen( $part ) === 1 ) {
        $script = array_search( $part, LanguageDerivation::SCRIPT_TOKENS, true );
      if ( $script !== false ) {
          return ucfirst( $script );
      }
    }

      return $part;
  }
}
