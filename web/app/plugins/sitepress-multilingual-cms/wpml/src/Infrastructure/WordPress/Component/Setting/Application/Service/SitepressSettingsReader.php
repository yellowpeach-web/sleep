<?php

namespace WPML\Infrastructure\WordPress\Component\Setting\Application\Service;

use WPML\Core\Port\Persistence\OptionsInterface;
use WPML\Infrastructure\WordPress\Component\CustomFieldPreferences\EarlyReadBoundary;

class SitepressSettingsReader {

  const SITEPRESS_OPTIONS      = 'icl_sitepress_settings';
  const TRANSLATION_MANAGEMENT = 'translation-management';

  private $options;


  public function __construct( OptionsInterface $options ) {
    $this->options = $options;
  }


  public function getGeneralSetting( string $optionName, $default = null ) {
    if ( $optionName === self::TRANSLATION_MANAGEMENT ) {
      return $default;
    }

    $settings = $this->readSettings();

    return $settings[ $optionName ] ?? $default;
  }


  public function getTranslationManagementSetting( string $optionName, $default = null ) {
    $translationManagement = $this->readSettings()[ self::TRANSLATION_MANAGEMENT ] ?? [];
    if ( ! is_array( $translationManagement ) ) {
      return $default;
    }

    return $translationManagement[ $optionName ] ?? $default;
  }


  private function readSettings(): array {
    $settings = EarlyReadBoundary::suspendDuring(
      fn() => $this->options->get( self::SITEPRESS_OPTIONS )
    );

    return is_array( $settings ) ? $settings : [];
  }


}
