<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;
use WPML_Media_Settings;

class MediaTranslationSettingsController implements PageRenderInterface {


  public function render() {
    wp_enqueue_script( 'wpml-settings-flash' );

    SettingsPageChrome::printTitle(
      /* translators: Name of the Media Translation section of WPML → Settings: its entry in the settings search list and its heading. */
      __( 'Media Translation', 'wpml' ),
      __( 'Configure how images and other media are duplicated to translated languages, and whether attached image texts (alt, caption, title) are included when translating content.', 'wpml' )
    );

    $wpdb = $GLOBALS['wpdb'] ?? null;
    if ( ! $wpdb ) {
      echo '<p>' . \wpml_bold_names( __( '<b>Media Translation</b> settings are unavailable.', 'wpml' ) ) . '</p>';
      return;
    }

    $settings = new WPML_Media_Settings( $wpdb );
    $settings->enqueue_script();

    ob_start();
    $settings->render();
    $body = (string) ob_get_clean();

    echo LegacySectionExtractor::dissolveHiddenCollision( $body );
  }


}
