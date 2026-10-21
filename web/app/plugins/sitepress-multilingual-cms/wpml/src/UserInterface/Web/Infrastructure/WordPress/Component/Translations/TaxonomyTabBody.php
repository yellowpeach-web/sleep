<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;
use WPML_Taxonomy_Translation_UI;
use WPML_UI_Screen_Options_Factory;

class TaxonomyTabBody implements PageRenderInterface {

  const TAB = 'taxonomy';

  private $ui = null;


  public function __construct() {
    $tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
    if ( $tab !== self::TAB ) {
      return;
    }

    $this->ui = $this->buildUi();
  }


  private function buildUi() {
    if ( ! class_exists( WPML_Taxonomy_Translation_UI::class ) ) {
      return null;
    }

    $sitepress = $GLOBALS['sitepress'] ?? null;
    if ( ! $sitepress ) {
      return null;
    }

    return new WPML_Taxonomy_Translation_UI(
      $sitepress,
      '',
      [],
      class_exists( WPML_UI_Screen_Options_Factory::class )
        ? new WPML_UI_Screen_Options_Factory( $sitepress )
        : null
    );
  }


  public function render() {
    if ( ! class_exists( WPML_Taxonomy_Translation_UI::class ) ) {
      echo '<p>' . esc_html__( 'Taxonomy translation is unavailable until WPML setup completes.', 'wpml' ) . '</p>';
      return;
    }

    $ui = $this->ui !== null ? $this->ui : $this->buildUi();
    if ( $ui === null ) {
      return;
    }

    ob_start();
    $ui->render();
    $uiHtml = (string) ob_get_clean();

    $uiHtml = (string) preg_replace(
      '#<h1[^>]*>[^<]*</h1>\s*<br\s*/?>\s*#',
      '',
      $uiHtml,
      1
    );

    echo $uiHtml;
  }


}
