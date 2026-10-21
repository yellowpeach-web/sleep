<?php

namespace WPML\Legacy\Component\Translator\Domain\Query;

use WPML\Core\SharedKernel\Component\Translator\Domain\LanguagePair;
use WPML\Core\SharedKernel\Component\Translator\Domain\Translator;

class SelfTranslatorProvider {

  private const SELF_TRANSLATION_CAPABILITIES = [
    'manage_options',
    'manage_translations',
    'wpml_manage_translation_management',
  ];


  public function get(): ?Translator {
    $currentUser = \wp_get_current_user();

    if ( ! $this->isValidCurrentUser( $currentUser ) || ! $this->canCurrentUserSelfTranslate() ) {
      return null;
    }

    $languagePairs = $this->getActiveLanguagePairs();

    if ( ! count( $languagePairs ) ) {
      return null;
    }

    return new Translator(
      $currentUser->ID,
      $currentUser->display_name,
      $currentUser->user_nicename,
      $languagePairs
    );
  }


  private function isValidCurrentUser( object $currentUser ): bool {
    return $currentUser->ID > 0;
  }


  private function canCurrentUserSelfTranslate(): bool {
    foreach ( self::SELF_TRANSLATION_CAPABILITIES as $capability ) {
      if ( \current_user_can( $capability ) ) {
        return true;
      }
    }

    return false;
  }


  private function getActiveLanguagePairs(): array {
    $languageCodes = $this->getActiveLanguageCodes();
    $languagePairs = [];

    foreach ( $languageCodes as $from ) {
      $to = $this->getTargetLanguageCodes( $languageCodes, $from );

      if ( count( $to ) ) {
        $languagePairs[] = new LanguagePair( $from, $to );
      }
    }

    return $languagePairs;
  }


  private function getActiveLanguageCodes(): array {
    $sitepress = $GLOBALS['sitepress'] ?? null;

    if ( ! is_object( $sitepress ) || ! method_exists( $sitepress, 'get_active_languages' ) ) {
      return [];
    }

    $activeLanguages = $sitepress->get_active_languages();
    $languageCodes   = [];

    foreach ( $activeLanguages as $languageCode => $language ) {
      if ( isset( $language['code'] ) && is_string( $language['code'] ) ) {
        $languageCodes[] = $language['code'];
      } else if ( is_string( $languageCode ) ) {
        $languageCodes[] = $languageCode;
      }
    }

    return array_values( array_unique( $languageCodes ) );
  }


  private function getTargetLanguageCodes( array $languageCodes, string $sourceLanguageCode ): array {
    return array_values(
      array_filter(
        $languageCodes,
        function ( string $languageCode ) use ( $sourceLanguageCode ) {
          return $languageCode !== $sourceLanguageCode;
        }
      )
    );
  }


}
