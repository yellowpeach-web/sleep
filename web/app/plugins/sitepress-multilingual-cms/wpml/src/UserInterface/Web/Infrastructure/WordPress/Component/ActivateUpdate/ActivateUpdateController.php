<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\ActivateUpdate;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;
use WP_Installer;

class ActivateUpdateController implements PageRenderInterface {


  public function render() {
    echo '<div class="wrap">';

    /* translators: Name of the WPML Activate & Update screen: the admin menu item and the page heading. */
    echo '<h1>' . esc_html__( 'Activate & Update', 'wpml' ) . '</h1>';
    echo '<p style="font-size:14px;color:#6b7280;margin:0 0 1.5em 0;">';
    echo esc_html__( 'Register this site, then install or update WPML and Toolset plugins.', 'wpml' );
    echo '</p>';

    if ( class_exists( WP_Installer::class ) && function_exists( 'WP_Installer' ) ) {
      WP_Installer()->show_products();
    } else {
      echo '<p>' . esc_html__(
        'The WPML Installer component is unavailable on this site. Please contact support.',
        'wpml'
      ) . '</p>';
    }

    echo '</div>';
  }


}
