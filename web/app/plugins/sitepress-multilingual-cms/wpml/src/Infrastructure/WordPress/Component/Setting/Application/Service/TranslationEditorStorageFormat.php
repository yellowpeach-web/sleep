<?php

namespace WPML\Infrastructure\WordPress\Component\Setting\Application\Service;

use WPML\Core\SharedKernel\Component\Setting\Domain\TranslationEditorSetting;

class TranslationEditorStorageFormat {

  const KEY = 'doc_translation_method';

  const STORED_ATE     = 'ATE';
  const STORED_CLASSIC = 1;
  const STORED_MANUAL  = 0;
  const STORED_PRO     = 2;

  const BAD_STRINGS = [
    TranslationEditorSetting::CLASSIC,
    TranslationEditorSetting::MANUAL,
    TranslationEditorSetting::PRO,
  ];

  const STORED_TO_DOMAIN = [
    'ATE'    => TranslationEditorSetting::ATE,
    '0'      => TranslationEditorSetting::MANUAL,
    '1'      => TranslationEditorSetting::CLASSIC,
    '2'      => TranslationEditorSetting::PRO,
    'CTE'    => TranslationEditorSetting::CLASSIC,
    'MANUAL' => TranslationEditorSetting::MANUAL,
    'PRO'    => TranslationEditorSetting::PRO,
  ];

  const DOMAIN_TO_STORED = [
    TranslationEditorSetting::ATE     => self::STORED_ATE,
    TranslationEditorSetting::CLASSIC => self::STORED_CLASSIC,
    TranslationEditorSetting::MANUAL  => self::STORED_MANUAL,
    TranslationEditorSetting::PRO     => self::STORED_PRO,
  ];


  public static function toDomain( string $stored ): string {
    return self::STORED_TO_DOMAIN[ $stored ];
  }


  public static function toStored( string $domain ) {
    if ( ! array_key_exists( $domain, self::DOMAIN_TO_STORED ) ) {
      throw new \InvalidArgumentException( esc_html( "Unknown translation editor '$domain'." ) );
    }

    return self::DOMAIN_TO_STORED[ $domain ];
  }


  public static function isBadString( $stored ): bool {
    return is_string( $stored ) && in_array( $stored, self::BAD_STRINGS, true );
  }


}
