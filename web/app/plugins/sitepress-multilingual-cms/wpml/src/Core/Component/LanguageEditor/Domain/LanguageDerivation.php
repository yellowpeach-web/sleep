<?php

namespace WPML\Core\Component\LanguageEditor\Domain;

class LanguageDerivation {

    const FLAG_NEUTRAL_GLOBE = 'nil.svg';

    const MAX_CODE_LENGTH = 7;

    const SCRIPT_TOKENS = [
        'arab' => 'a',
        'cans' => 'c',
        'cyrl' => 'y',
        'deva' => 'd',
        'guru' => 'g',
        'hans' => 's',
        'hant' => 't',
        'latn' => 'l',
        'mong' => 'm',
    ];

    const DEFAULT_SCRIPT = [
        'sr' => 'cyrl',
        'az' => 'latn',
        'bs' => 'latn',
        'uz' => 'latn',
        'ms' => 'latn',
        'kk' => 'cyrl',
        'ku' => 'arab',
        'mn' => 'cyrl',
        'pa' => 'guru',
    ];

    const LOCALE_SCRIPT = [
        'sr' => 'cyrl',
    ];

    const SCRIPT_COUNTRIES = [
        'az' => [ 'arab' => [ 'IQ' ], 'cyrl' => [ 'AZ', 'RU' ], 'latn' => [ 'AZ', 'IR', 'TR' ] ],
        'bs' => [ 'cyrl' => [ 'BA' ], 'latn' => [ 'BA' ] ],
        'iu' => [ 'cans' => [ 'CA' ], 'latn' => [ 'CA' ] ],
        'kk' => [ 'arab' => [], 'cyrl' => [ 'KZ' ] ],
        'ku' => [ 'arab' => [], 'latn' => [ 'IQ' ] ],
        'mn' => [ 'cyrl' => [ 'MN' ], 'mong' => [ 'CN' ] ],
        'ms' => [ 'arab' => [ 'BN' ], 'latn' => [ 'MY', 'SG', 'BN' ] ],
        'pa' => [ 'arab' => [], 'guru' => [ 'IN' ] ],
        'sd' => [ 'arab' => [ 'IN' ], 'deva' => [ 'IN' ] ],
        'sr' => [ 'cyrl' => [ 'RS', 'BA', 'XK' ], 'latn' => [ 'RS', 'ME', 'BA', 'XK' ] ],
        'uz' => [ 'arab' => [ 'AF' ], 'cyrl' => [ 'UZ' ], 'latn' => [ 'UZ' ] ],
        'zh' => [ 'hans' => [ 'CN', 'SG' ], 'hant' => [ 'TW', 'HK', 'MO' ] ],
    ];


    public static function baseIdentifier( $language, $script = null ) {
        $language = strtolower( trim( $language ) );
        $script   = $script !== null ? strtolower( trim( $script ) ) : '';

        return $script !== '' ? $language . '-' . $script : $language;
    }


    public static function languageHead( $code ) {
        $parts = explode( '-', strtolower( trim( $code ) ) );
        $lang  = $parts[0];

      if ( isset( $parts[1] ) ) {
        if ( strlen( $parts[1] ) === 4 ) {
            return $lang . '-' . $parts[1];
        }
        $token = array_search( $parts[1], self::SCRIPT_TOKENS, true );
        if ( $token !== false ) {
            return $lang . '-' . $token;
        }
      }

        return isset( self::DEFAULT_SCRIPT[ $lang ] ) ? $lang . '-' . self::DEFAULT_SCRIPT[ $lang ] : $lang;
    }


    public static function isPrimaryLocaleScript( $language, $script ) {
        $lang = strtolower( $language );
      if ( isset( self::LOCALE_SCRIPT[ $lang ] ) ) {
          $primary = self::LOCALE_SCRIPT[ $lang ];
      } elseif ( isset( self::DEFAULT_SCRIPT[ $lang ] ) ) {
          $primary = self::DEFAULT_SCRIPT[ $lang ];
      } else {
          return false;
      }

        return $primary === strtolower( (string) $script );
    }


    public static function customCanonicalCode(
        $typedDisplayCode,
        array $existingCodes,
        $reserved = null,
        $reservedForTyped = null
    ) {
        $taken = self::normalizeSet( $existingCodes );
        $base  = strtolower( trim( $typedDisplayCode ) );
      if ( $base === '' ) {
          $base = 'lang';
      }

      if ( strlen( $base ) <= self::MAX_CODE_LENGTH && ! isset( $taken[ $base ] )
        && ( $reservedForTyped === null || ! $reservedForTyped( $base ) ) ) {
          return $base;
      }

        return self::numericUntilUnique( $base, $taken, $reserved );
    }


    public static function defaultFlagFile(
        $language, $script, $country, $countryFlag, $languageFlag, $isRegional = false
    ) {
        $country      = $country !== null ? strtolower( trim( $country ) ) : '';
        $countryFlag  = $countryFlag !== null ? trim( $countryFlag ) : '';
        $languageFlag = $languageFlag !== null ? trim( $languageFlag ) : '';

      if ( $isRegional && $languageFlag !== '' ) {
          return $languageFlag;
      }

      if ( $country !== '' && $countryFlag !== '' ) {
          return $countryFlag;
      }

      if ( $languageFlag !== '' ) {
          return $languageFlag;
      }

        return self::FLAG_NEUTRAL_GLOBE;
    }


    private static function normalizeSet( array $codes ) {
        $set = [];
      foreach ( $codes as $code ) {
          $set[ strtolower( trim( $code ) ) ] = true;
      }

        return $set;
    }


    private static function numericUntilUnique( $base, array $taken, $reserved = null ) {
        $base = strtolower( $base );
        $max  = self::MAX_CODE_LENGTH;
        $free = static function ( string $candidate ) use ( $taken, $reserved ): bool {
            return ! isset( $taken[ $candidate ] )
              && ( $reserved === null || ! $reserved( $candidate ) );
        };

        $trimmed = strlen( $base ) > $max ? (string) substr( $base, 0, $max ) : $base;
      if ( $trimmed !== '' && $free( $trimmed ) ) {
          return $trimmed;
      }

        $n = 2;
      do {
          $suffix    = (string) $n;
          $head      = (string) substr( $base, 0, max( 0, $max - strlen( $suffix ) ) );
          $candidate = ( $head !== '' ? $head : 'x' ) . $suffix;
          $n++;
      } while ( ! $free( $candidate ) );

        return $candidate;
    }


}
