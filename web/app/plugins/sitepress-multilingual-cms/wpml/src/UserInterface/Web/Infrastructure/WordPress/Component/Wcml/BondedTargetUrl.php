<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Wcml;

class BondedTargetUrl implements \IWPML_Backend_Action {


  const SUPPORT_PAGE_SLUG  = 'sitepress-multilingual-cms/menu/support.php';
  const SETTINGS_PAGE_SLUG = 'tm/menu/settings';
  const TRANSLATIONS_SLUG  = 'tm/menu/main.php';


  public function add_hooks() {
    add_filter( 'wcml_bonded_target_url', array( $this, 'resolve' ), 10, 2 );
  }


  public function resolve( $url, $key ) {
    if ( ! is_string( $key ) ) {
      return $url;
    }

    $resolved = $this->urlFor( $key );

    return $resolved !== '' ? $resolved : $url;
  }


  private function urlFor( string $key ): string {
    switch ( $key ) {
      case 'settings':
        return admin_url( 'admin.php?page=' . self::SETTINGS_PAGE_SLUG . '&section=wcml' );

      case 'multi-currency':
        return admin_url( 'admin.php?page=' . self::SETTINGS_PAGE_SLUG . '&section=multi-currency' );

      case 'store-url':
        return admin_url( 'admin.php?page=' . self::TRANSLATIONS_SLUG . '&tab=store-urls' );

      case 'status':
        return admin_url( 'admin.php?page=' . self::SUPPORT_PAGE_SLUG . '&tool=wcml-status' );

      case 'support':
        return admin_url( 'admin.php?page=' . self::SUPPORT_PAGE_SLUG );

      case 'troubleshooting-safe':
        return admin_url( 'admin.php?page=' . self::SUPPORT_PAGE_SLUG . '&tool=wcml-fix-translations' );

      case 'troubleshooting-config':
        return admin_url( 'admin.php?page=' . self::SUPPORT_PAGE_SLUG . '&tool=wcml-config-log' );

      case 'troubleshooting-advanced':
        return admin_url( 'admin.php?page=' . self::SUPPORT_PAGE_SLUG . '&tool=wcml-translation-maintenance' );

      default:
        return '';
    }
  }


}
