<?php

namespace WPML\Core\Component\LanguageEditor\Domain;

final class LanguagePairKey {

  public static function forHead( string $head, ?string $country ): string {
      return strtolower( $head ) . '|' . strtoupper( (string) $country );
  }


  public static function headOf( array $pair ): string {
      return isset( $pair['head'] ) && $pair['head'] !== ''
        ? $pair['head']
        : $pair['code'];
  }


  public static function forPreset( string $language, ?string $script, ?string $country ): string {
      $head = LanguageDerivation::languageHead( LanguageDerivation::baseIdentifier( $language, $script ) );

      return strtolower( $head ) . '|' . strtoupper( (string) $country );
  }


  public static function languageSubtag( string $presetCode, ?string $script ): string {
    if ( $script ) {
        $suffix = '-' . strtolower( $script );
      if ( substr( $presetCode, - strlen( $suffix ) ) === $suffix ) {
        return substr( $presetCode, 0, - strlen( $suffix ) );
      }
    }

      return $presetCode;
  }
}
