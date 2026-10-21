<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;
use WPML_Media_Menus_Factory;

class MediaTabBody implements PageRenderInterface {


  public function render() {
    if ( ! class_exists( WPML_Media_Menus_Factory::class ) ) {
      $message = \wpml_bold_names(
        __( 'Media translation is unavailable until WPML Media Translation is activated.', 'wpml' )
      );
      echo '<p>' . $message . '</p>';
      return;
    }

    $factory = new WPML_Media_Menus_Factory();
    $menus   = $factory->create();
    if ( ! $menus ) {
      return;
    }

    $menus->display();
  }


}
