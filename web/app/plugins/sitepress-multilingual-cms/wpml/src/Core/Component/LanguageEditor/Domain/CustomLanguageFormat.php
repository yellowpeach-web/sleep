<?php

namespace WPML\Core\Component\LanguageEditor\Domain;

final class CustomLanguageFormat {

    const DISPLAY_CODE_FORMAT = '/^(?!0$)[A-Za-z0-9_-]+$/';

    const HREFLANG_MAX_LENGTH = 35;

    const LOCALE_FORMAT = '/^[A-Za-z0-9_]+$/';

    const MAX_CODE_LENGTH = LanguageDerivation::MAX_CODE_LENGTH;

    const DISPLAY_CODE_MAX_LENGTH = 40;


  public static function codeSpace(): array {
      return [
          'codePattern'      => self::barePattern( self::DISPLAY_CODE_FORMAT ),
          'codeFlags'        => self::pcreFlags( self::DISPLAY_CODE_FORMAT ),
          'maxCodeLength'    => self::DISPLAY_CODE_MAX_LENGTH,
          'hreflangMaxLength' => self::HREFLANG_MAX_LENGTH,
          'localePattern'    => self::barePattern( self::LOCALE_FORMAT ),
          'localeFlags'      => self::pcreFlags( self::LOCALE_FORMAT ),
      ];
  }


  private static function barePattern( string $pcre ): string {
      return substr( $pcre, 1, (int) strrpos( $pcre, '/' ) - 1 );
  }


  private static function pcreFlags( string $pcre ): string {
      return substr( $pcre, (int) strrpos( $pcre, '/' ) + 1 );
  }


  public static function validateCode( string $code ): string {
    if ( $code === '' ) {
        return '';
    }
    if ( ! preg_match( self::DISPLAY_CODE_FORMAT, $code ) ) {
        return 'code_invalid_format';
    }
    if ( strlen( $code ) > self::DISPLAY_CODE_MAX_LENGTH ) {
        return 'code_too_long';
    }

      return '';
  }


  public static function usableDisplayCode( $stored ): string {
    if ( ! is_scalar( $stored ) ) {
        return '';
    }
      $value = (string) $stored;

      return ( '' === trim( $value ) || '0' === trim( $value ) ) ? '' : $value;
  }


  public static function validateLocale( string $locale ): string {
    if ( $locale === '' ) {
        return '';
    }

      return preg_match( self::LOCALE_FORMAT, $locale ) ? '' : 'locale_invalid_format';
  }


  public static function validateHreflang( string $hreflang ): string {
    if ( strlen( $hreflang ) > self::HREFLANG_MAX_LENGTH ) {
        return 'hreflang_too_long';
    }

      return '';
  }


}
