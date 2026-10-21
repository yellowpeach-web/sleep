<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;

class DataSharingController implements PageRenderInterface {

  const SECTION_CLASS = '\WPML\DataSharing\DataSharingSection';


  public function render() {
    wp_enqueue_script( 'wpml-settings-flash' );

    SettingsPageChrome::printTitle(
      /* translators: Name of the Data sharing section of WPML → Settings: its entry in the settings search list and its heading. */
      __( 'Data sharing', 'wpml' ),
      ''
    );

    if ( ! $this->sectionIsRenderable() ) {
      echo '<p>' . esc_html__( 'Data sharing settings are unavailable until WPML setup completes.', 'wpml' ) . '</p>';
      return;
    }

    \WPML\DataSharing\DataSharingSection::render( 'h2' );
  }


  private function sectionIsRenderable(): bool {
    if ( ! class_exists( self::SECTION_CLASS ) ) {
      return false;
    }

    return \WPML\DataSharing\DataSharingSection::isAvailable();
  }


}
