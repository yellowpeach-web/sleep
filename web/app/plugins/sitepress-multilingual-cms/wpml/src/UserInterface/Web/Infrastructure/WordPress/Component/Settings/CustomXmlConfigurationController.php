<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;

class CustomXmlConfigurationController implements PageRenderInterface {


  public function render() {
    wp_enqueue_script( 'wpml-settings-flash' );

    SettingsPageChrome::printTitle(
      __( 'Custom XML Configuration', 'wpml' ),
      __( 'Override the wpml-config.xml file in the root folder of your theme and plugins, which tells WPML which content is translatable.', 'wpml' )
    );

    echo '<div class="wpml-section">';
    echo '<div class="wpml-section-content">';
    echo '<div id="wpml-tm-custom-xml-content" class="wpml-tm-custom-xml js-wpml-tm-custom-xml"></div>';
    echo '</div>';
    echo '</div>';
  }


}
