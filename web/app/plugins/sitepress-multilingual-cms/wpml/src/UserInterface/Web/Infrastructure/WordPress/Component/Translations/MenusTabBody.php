<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations;

use ICLMenusSync;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\DashboardRequirements;
use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;

class MenusTabBody implements PageRenderInterface {

  const PARTIAL = '/menu/menu-sync/menus-sync.php';
  const SYNC_CLASS_FILE = '/inc/wp-nav-menus/menus-sync.php';

  /**
   * The Translation Management gate the tab strip reads: a Blog licence
   * grants Menus but not the Translation Dashboard (wpmldev-8438).
   *
   * @var DashboardRequirements
   */
  private $tmAllowed;


  public function __construct( DashboardRequirements $tmAllowed ) {
    $this->tmAllowed = $tmAllowed;
  }


  public function render() {
    global $icl_menus_sync, $sitepress, $wpdb, $wpml_post_translations, $wpml_term_translations;

    if ( ! defined( 'WPML_PLUGIN_PATH' ) || ! $sitepress ) {
      $message = esc_html__( 'Menus synchronization is unavailable until WPML setup completes.', 'wpml' );
      echo '<p>' . $message . '</p>';
      return;
    }

    if ( empty( wp_get_nav_menus() ) && ! current_theme_supports( 'menus' ) ) {
      $this->renderNoClassicMenusMessage();
      return;
    }

    $classFile = WPML_PLUGIN_PATH . self::SYNC_CLASS_FILE;
    if ( ! is_readable( $classFile ) ) {
      return;
    }

    require_once $classFile;

    if ( $icl_menus_sync === null && class_exists( ICLMenusSync::class ) ) {
      $icl_menus_sync = new ICLMenusSync( $sitepress, $wpdb, $wpml_post_translations, $wpml_term_translations );

      $icl_menus_sync->init();
    }

    ob_start();
    include WPML_PLUGIN_PATH . self::PARTIAL;
    $partialHtml = (string) ob_get_clean();

    $partialHtml = (string) preg_replace(
      '#(<div\s+class="wrap[^"]*"[^>]*>\s*)<h2[^>]*>[^<]*</h2>\s*#',
      '$1',
      $partialHtml,
      1
    );

    echo $partialHtml;
  }


  private function renderNoClassicMenusMessage() {
    $theme     = wp_get_theme();
    $themeName = $theme->get( 'Name' );
    if ( ! is_string( $themeName ) || $themeName === '' ) {
      /* translators: Used on WPML → Translations in place of the theme name when that name cannot be read. Lower case because it sits inside a sentence. */
      $themeName = __( 'your active theme', 'wpml' );
    }

    $dashboardUrl  = admin_url( 'admin.php?page=tm/menu/main.php&tab=dashboard' );
    $siteEditorUrl = admin_url( 'site-editor.php' );

    echo '<div class="wrap wpml-tm-menus-sync">';

    echo '<p>';
    echo wp_kses(
      sprintf(
        // translators: %s is the active theme name (e.g. "Twenty Twenty-Five").
        __( 'Your active theme <strong>%s</strong> doesn\'t use the legacy <em>Appearance &rarr; Menus</em> screen, and no classic menus exist on this site yet.', 'wpml' ),
        esc_html( $themeName )
      ),
      [ 'strong' => [], 'em' => [] ]
    );
    echo '</p>';

    echo '<p>';
    echo wp_kses(
      sprintf(
        // translators: %s is the URL to the WordPress Site Editor.
        __( 'Block themes manage navigation through the <a href="%s">Site Editor</a> — each menu is stored as a Navigation entry rather than a classic menu. WPML treats those Navigation entries as regular translatable content.', 'wpml' ),
        esc_url( $siteEditorUrl )
      ),
      [ 'a' => [ 'href' => [] ] ]
    );
    echo '</p>';

    // The Dashboard sentence only where the licence has a Dashboard: on a
    // Blog licence the tab strip hides it, so the link led straight back
    if ( $this->tmAllowed->requirementsMet() ) {
      echo '<p>';
      echo wp_kses(
        sprintf(
          /* translators: Message on WPML → Translations. "Them" are the site's navigation menus. %s: the web address of the Translation Dashboard. Keep the HTML tags as they are. */
          __( 'To translate them, switch to the <a href="%s">Dashboard</a> — each menu can be translated under the <strong>Navigation Menus</strong> section.', 'wpml' ),
          esc_url( $dashboardUrl )
        ),
        [
          'a'      => [ 'href' => [] ],
          'strong' => [],
        ]
      );
      echo '</p>';
    }

    echo '</div>';
  }


}
