<?php

namespace WPML\UserInterface\Web\Infrastructure\CompositionRoot\Config\Updates;

class ProgressNotice {
  const CSS_ID = 'wpml-updates-progress-notice';

  const PAGE_PREFIXES = [ 'sitepress-multilingual-cms', 'wpml', 'tm/menu' ];

  private $percent = null;


  public function show( $percent ) {
    if ( $this->percent === null ) {
      add_action( 'all_admin_notices', [ $this, 'render' ] );
    }

    $this->percent = $percent;
  }


  public function render() {
    if ( $this->percent === null || ! $this->isWpmlScreen() ) {
      return;
    }

    echo '<div id="' . esc_attr( self::CSS_ID ) . '" class="notice notice-info"><p>'
      . esc_html(
        sprintf(
          /* translators: %d: how much of the update is done, from 0 to 99 */
          __( 'WPML is still updating its data after the upgrade: %d%% done. This continues in the background.', 'wpml' ),
          $this->percent
        )
      )
      . '</p></div>';
  }


  private function isWpmlScreen() {
    $page = $GLOBALS['plugin_page'] ?? '';
    $page = is_string( $page ) ? $page : '';

    foreach ( self::PAGE_PREFIXES as $prefix ) {
      if ( strpos( $page, $prefix ) === 0 ) {
        return true;
      }
    }

    return false;
  }


}
