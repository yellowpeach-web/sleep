<?php

namespace WPML\Core\Component\WordsToTranslate\Domain\Post;

class FieldKey {

  const PACKAGE_STRING_FIELD_PREFIX = 'package-string';

  const PACKAGE_STRINGS_GROUP_KEY = 'package-strings';

  const CUSTOM_FIELD_PREFIX = 'field-';


  public static function groupKey( string $fieldType ): string {
    if ( strpos( $fieldType, self::PACKAGE_STRING_FIELD_PREFIX ) === 0 ) {
      return self::PACKAGE_STRINGS_GROUP_KEY;
    }

    if ( strpos( $fieldType, self::CUSTOM_FIELD_PREFIX ) === 0 ) {
      return preg_replace( '/(-\d+)+$/', '', $fieldType ) ?? $fieldType;
    }

    return $fieldType;
  }


}
