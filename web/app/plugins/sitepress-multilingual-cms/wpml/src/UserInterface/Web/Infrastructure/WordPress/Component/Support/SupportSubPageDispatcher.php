<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support;

use WPML\Core\SharedKernel\Component\Support\Application\Repository\RepairToolsPluginInterface;
use WPML\UserInterface\Web\Core\Component\Support\Application\SupportToolRegistryInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;


class SupportSubPageDispatcher implements PageRenderInterface {

  private $landing;

  private $registry;


  private $repairToolsPlugin;


  public function __construct(
    SupportLandingController $landing,
    SupportToolRegistryInterface $registry,
    RepairToolsPluginInterface $repairToolsPlugin
  ) {
    $this->landing           = $landing;
    $this->registry          = $registry;
    $this->repairToolsPlugin = $repairToolsPlugin;
  }


  public function render() {
      $tool = $this->currentTool();

    if ( $tool === '' ) {
        $this->landing->render();
        return;
    }

      $sub = $this->resolveTool( $tool );
    if ( $sub === null && $this->isRepairToolWithoutItsPlugin( $tool ) ) {
        echo '<div class="wrap wpml-support-page">';
        echo '<div class="wpml:max-w-3xl wpml:mt-6 wpml:pb-16">';
        $this->renderBackLink();
        ( new MissingRepairToolPage( $tool, $this->repairToolsPlugin ) )->render();
        echo '</div>';
        echo '</div>';
        return;
    }
    if ( $sub === null ) {
        $this->landing->renderForUnknownTool( $tool );
        return;
    }

      wp_enqueue_script( 'wpml-settings-flash' );

      echo '<div class="wrap wpml-support-page">';
      echo '<div class="' . esc_attr( $this->widthClassFor( $sub ) ) . ' wpml:mt-6 wpml:pb-16">';
      $this->renderBackLink();
      $sub->render();
      echo '</div>';
      echo '</div>';
  }


  private function widthClassFor( PageRenderInterface $sub ): string {
      $class    = get_class( $sub );
      $constant = $class . '::WIDTH_CLASS';
    if ( defined( $constant ) ) {
      try {
        $value = constant( $constant );
      } catch ( \Error $e ) {
        $value = null;
      }
      if ( is_string( $value ) ) {
        return $value;
      }
    }
      return 'wpml:max-w-3xl';
  }


  /**
   * A slug WPML Troubleshooting would register, on a site where nothing is
   * registered under that plugin's provider - i.e. the plugin is absent, not
   * merely withholding the tool from this user or licence.
   */
  private function isRepairToolWithoutItsPlugin( string $tool ): bool {
    if ( ! MissingRepairToolPage::knows( $tool ) ) {
        return false;
    }
    foreach ( $this->registry->all() as $entry ) {
      if ( $entry->provider() === SupportLandingController::REPAIR_TOOLS_PROVIDER ) {
          return false;
      }
    }
      return true;
  }


  private function currentTool(): string {
      $raw = isset( $_GET['tool'] ) ? $_GET['tool'] : '';
    if ( ! is_string( $raw ) ) {
        return '';
    }
      return preg_match( '/^[a-z0-9-]+$/', $raw ) === 1 ? $raw : '';
  }


    /**
     * The registry answers null for an unknown slug, for a tool the current
     * user lacks the capability for, and for a TM tool on a blog license
     * (`WPML_TM_VERSION` undefined, wpmldev-7163) — every one of those falls
     * back to the landing rather than rendering a tool the site does not
     * include.
     *
     * @return PageRenderInterface|null
     */
  private function resolveTool( string $tool ) {
      $entry = $this->registry->find( $tool );

      return $entry === null ? null : $entry->controller();
  }


  private function renderBackLink(): void {
      $href = esc_url(
        admin_url( 'admin.php?page=sitepress-multilingual-cms/menu/support.php' )
      );

      echo '<p class="wpml-support-back" style="margin:0 0 1em 0;">';
      echo '<a href="' . $href . '" class="wpml:inline-flex wpml:items-center wpml:gap-1.5 wpml:text-sm wpml:text-blue wpml:hover:underline">';
      echo '<svg class="wpml:w-4 wpml:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">';
      echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>';
      echo '</svg>';
      /* translators: Name of the WPML Support screen: the admin menu item, the page heading, and the back-link that returns to it. Noun (help from the WPML support team), not the verb "to support". */
      echo esc_html__( 'Support', 'wpml' );
      echo '</a></p>';
  }


}
