<?php

namespace WPML\Core\SharedKernel\Component\Language\Domain;

final class LanguageCode {

  const ENGLISH = 'en';


  public static function languagePart( $code ): string {
    $code = strtolower( trim( (string) $code ) );
    if ( '' === $code ) {
      return '';
    }

    $parts = preg_split( '/[-_]/', $code, 2 );

    return is_array( $parts ) ? $parts[0] : $code;
  }


  public static function isEnglish( $code ): bool {
    return self::ENGLISH === self::languagePart( $code );
  }


  public static function isEnglishTargetOfEnglishSource( $sourceCode, $targetCode ): bool {
    return self::isEnglish( $sourceCode ) && self::isEnglish( $targetCode );
  }


  public static function englishSourceCode( array $activeCodes, string $defaultCode ): string {
    $activeCodes = array_map( 'strval', $activeCodes );
    if ( in_array( self::ENGLISH, $activeCodes, true ) ) {
      return self::ENGLISH;
    }

    $variants = array_values( array_filter( $activeCodes, [ self::class, 'isEnglish' ] ) );
    sort( $variants );

    if ( [] === $variants ) {
      return self::ENGLISH;
    }

    return in_array( $defaultCode, $variants, true ) ? $defaultCode : $variants[0];
  }


}
