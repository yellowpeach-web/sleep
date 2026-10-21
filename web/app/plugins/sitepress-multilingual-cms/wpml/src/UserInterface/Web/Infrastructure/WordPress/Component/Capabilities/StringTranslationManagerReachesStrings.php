<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Capabilities;

class StringTranslationManagerReachesStrings implements \IWPML_Backend_Action {

  const TRANSLATIONS_PAGE_SLUG = 'tm/menu/main.php';
  const STRINGS_TAB            = 'strings';
  const GRANT                  = 'wpml_manage_string_translation';

  private $queryParam;

  private $isAdminPageLoad;


  public function __construct( ?callable $queryParam = null, ?callable $isAdminPageLoad = null ) {
    $this->queryParam = $queryParam ?: static function ( string $key ): string {
      $value = filter_input( INPUT_GET, $key, FILTER_UNSAFE_RAW );
      return is_string( $value ) ? $value : '';
    };
    $this->isAdminPageLoad = $isAdminPageLoad ?: static function (): bool {
      if ( ! isset( $GLOBALS['pagenow'] ) || 'admin.php' !== $GLOBALS['pagenow'] ) {
        return false;
      }
      if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
        return false;
      }
      if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
        return false;
      }
      if ( defined( 'REST_REQUEST' ) && (bool) constant( 'REST_REQUEST' ) ) {
        return false;
      }
      return true;
    };
  }


  public function add_hooks(): void {
    add_filter( 'user_has_cap', array( $this, 'grantTranslate' ), 10, 4 );
    add_action( 'admin_head', array( $this, 'stopGranting' ), 0 );
  }


  public function stopGranting(): void {
    remove_filter( 'user_has_cap', array( $this, 'grantTranslate' ), 10 );
  }


  public function grantTranslate( $allcaps, $caps, $args, $user ) {
    if ( ! in_array( 'translate', $caps, true ) ) {
      return $allcaps;
    }
    if ( empty( $allcaps[ self::GRANT ] ) ) {
      return $allcaps;
    }
    if ( ! $this->isStringsTabRequest() ) {
      return $allcaps;
    }
    $allcaps['translate'] = true;
    return $allcaps;
  }


  private function isStringsTabRequest(): bool {
    $isPageLoad = $this->isAdminPageLoad;
    if ( ! $isPageLoad() ) {
      return false;
    }

    $param = $this->queryParam;

    return self::TRANSLATIONS_PAGE_SLUG === $param( 'page' )
      && self::STRINGS_TAB === $param( 'tab' );
  }


}
