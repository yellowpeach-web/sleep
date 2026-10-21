<?php

namespace WPML\Core\Component\Cdt\Domain;

class TranslationMode {

  const TRANSLATABLE          = 'translatable';
  const DISPLAY_AS_TRANSLATED = 'display-as-translated';
  const NOT_TRANSLATABLE      = 'not-translatable';


  public static function of( string $postType, array $translatable, array $displayAsTranslated ): string {
    if ( in_array( $postType, $displayAsTranslated, true ) ) {
      return self::DISPLAY_AS_TRANSLATED;
    }

    return in_array( $postType, $translatable, true ) ? self::TRANSLATABLE : self::NOT_TRANSLATABLE;
  }


}
