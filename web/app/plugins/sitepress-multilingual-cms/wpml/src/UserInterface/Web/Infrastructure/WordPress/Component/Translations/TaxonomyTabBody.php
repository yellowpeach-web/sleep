<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations;

use WPML\UserInterface\Web\Core\Component\Dashboard\Application\DashboardRequirements;
use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;
use WPML_Taxonomy_Translation_UI;
use WPML_UI_Screen_Options_Factory;

class TaxonomyTabBody implements PageRenderInterface {

  const TAB = 'taxonomy';

  private $ui = null;

  private $dashboard;


  public function __construct( DashboardRequirements $dashboard ) {
    $this->dashboard = $dashboard;

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

    $this->renderDashboardLine();

    echo $uiHtml;
  }


  /**
   * What this tab is for, under its title (wpmldev-12099, 5-5 S3): terms are
   * translated here by hand, and sent for translation from the Dashboard.
   * Where the viewer has no Dashboard (a Blog licence, or a user who holds
   * only `wpml_manage_taxonomy_translation`) the line is left out, so it never
   * links to a tab that is not there (wpmldev-8438).
   */
  private function renderDashboardLine(): void {
    if ( ! current_user_can( 'manage_translations' ) || ! $this->dashboard->requirementsMet() ) {
      return;
    }

    $sentence = sprintf(
      /* translators: Line under the Taxonomy tab title on WPML → Translations. %1$s and %2$s are the start and end of a link to the Translation Dashboard. */
      esc_html__( 'Edit term translations by hand, one term at a time. To send terms for translation, use the %1$sTranslation Dashboard%2$s.', 'wpml' ),
      '<a href="' . esc_url( admin_url( 'admin.php?page=tm/menu/main.php' ) ) . '">',
      '</a>'
    );

    echo '<p class="wpml-taxonomy-tab-description">' . wp_kses( $sentence, [ 'a' => [ 'href' => [] ] ] ) . '</p>';
  }


}
