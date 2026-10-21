<?php

namespace WPML\Core\Component\CustomFieldPreferences\Domain;

final class ElementType {

  const POST = 'post';
  const TERM = 'term';

  const SETTINGS_KEY = 'translation-management';

  const BLOB_KEYS = [
    self::POST => 'custom_fields_translation',
    self::TERM => 'custom_term_fields_translation',
  ];


  public static function all(): array {
    return [ self::POST, self::TERM ];
  }


}
