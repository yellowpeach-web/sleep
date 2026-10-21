<?php

namespace WPML\Legacy\Component\Cdt\Query;

use WPML\Core\Component\Cdt\Application\Query\SiteStateQueryInterface;
use WPML\Core\Component\Cdt\Domain\LanguageCode;
use WPML\Core\Component\ReportContentStats\Application\Query\CanCollectStatsQueryInterface;
use WPML\Core\Port\Persistence\OptionsInterface;

class SiteStateQuery implements SiteStateQueryInterface {

  const SETUP_OPTION_GROUP   = 'WPML(setup)';
  const TRANSLATE_EVERYTHING = 'translate-everything';

  const WOOCOMMERCE_PLUGIN = 'woocommerce/woocommerce.php';
  const WOOCOMMERCE_CLASS  = 'WooCommerce';

  const STORED_ATE = 'ATE';

  private $sitepress;

  private $options;

  private $consent;

  private $tmSubSetting;


  public function __construct( \SitePress $sitepress, OptionsInterface $options, CanCollectStatsQueryInterface $consent, $tmSubSetting = null ) {
    $this->sitepress    = $sitepress;
    $this->options      = $options;
    $this->consent      = $consent;
    $this->tmSubSetting = is_callable( $tmSubSetting ) ? $tmSubSetting : [ $this, 'hostTmSubSetting' ];
  }


  public function getWpmlVersion(): string {
    return defined( 'ICL_SITEPRESS_VERSION' ) ? (string) substr( (string) ICL_SITEPRESS_VERSION, 0, 16 ) : '';
  }


  public function isSetupComplete(): bool {
    return (bool) $this->sitepress->get_setting( 'setup_complete' );
  }


  public function getDefaultLanguage(): ?string {
    if ( ! $this->isSetupComplete() ) {
      return null;
    }

    $code = $this->sitepress->get_default_language();

    return LanguageCode::isValid( $code ) ? (string) $code : null;
  }


  public function getLanguages(): ?array {
    if ( ! $this->isSetupComplete() ) {
      return null;
    }

    $codes   = [];
    $default = $this->getDefaultLanguage();
    if ( $default !== null ) {
      $codes[] = $default;
    }

    $active = $this->sitepress->get_active_languages();
    foreach ( is_array( $active ) ? $active : [] as $key => $language ) {
      $code = is_array( $language ) && isset( $language['code'] ) ? $language['code'] : $key;
      if ( LanguageCode::isValid( $code ) && ! in_array( $code, $codes, true ) ) {
        $codes[] = (string) $code;
      }
    }

    return array_slice( $codes, 0, LanguageCode::MAX_LANGUAGES );
  }


  public function isTranslateEverythingOn(): ?bool {
    if ( ! $this->isSetupComplete() ) {
      return null;
    }

    $group = $this->options->get( self::SETUP_OPTION_GROUP, [] );

    return is_array( $group ) && ! empty( $group[ self::TRANSLATE_EVERYTHING ] );
  }


  public function getEditor(): ?string {
    if ( ! $this->isSetupComplete() ) {
      return null;
    }

    $stored = call_user_func( $this->tmSubSetting, 'doc_translation_method' );
    if ( ! is_scalar( $stored ) ) {
      return null;
    }

    return (string) $stored === self::STORED_ATE ? 'ate' : 'classic';
  }


  public function hostTmSubSetting( string $key ) {
    if ( function_exists( 'wpml_get_tm_sub_setting' ) ) {
      return wpml_get_tm_sub_setting( $key, null );
    }

    return null;
  }


  public function isWooCommerceActive(): bool {
    if ( class_exists( self::WOOCOMMERCE_CLASS ) ) {
      return true;
    }

    $plugins = $this->options->get( 'active_plugins', [] );

    return is_array( $plugins ) && in_array( self::WOOCOMMERCE_PLUGIN, $plugins, true );
  }


  public function hasConsent(): bool {
    return $this->consent->get();
  }


}
