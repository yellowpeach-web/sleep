<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations;

use WPML\UserInterface\Web\Core\Component\Dashboard\Application\DashboardController;
use WPML\UserInterface\Web\Core\SharedKernel\Config\Page;
use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Ate\AmsWidgetEmbed;

class DashboardTabBody implements PageRenderInterface {

  private $dashboard;

  private $page;


  public function __construct( DashboardController $dashboard ) {
    $this->dashboard = $dashboard;

    $tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : '';
    if ( ( $tab === '' || $tab === 'dashboard' ) && self::ateEnabled() ) {
      add_action( 'admin_enqueue_scripts', array( AmsWidgetEmbed::class, 'enqueue' ) );
    }
  }


  private static function ateEnabled(): bool {
    return \WPML_TM_ATE_Status::is_enabled();
  }


  public function setPage( Page $page ): void {
    $this->page = $page;
  }


  public function dashboard(): DashboardController {
    return $this->dashboard;
  }


  public function render() {
    if ( $this->page === null ) {
      return;
    }


    echo $this->page->getHtmlScriptRootContainers();
  }


}
