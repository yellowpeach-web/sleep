<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;

class TranslationEditorController implements PageRenderInterface {

  const SECTION_ID = 'ml-content-setup-sec-1';


  public function render() {
    wp_enqueue_script( 'wpml-settings-flash' );

    SettingsPageChrome::printTitle(
      /* translators: Name of the Translation Editor section of WPML → Settings: its entry in the settings search list and its heading. */
      __( 'Translation Editor', 'wpml' ),
      __( 'Choose between the Advanced and Classic editors, and configure editor-wide preferences.', 'wpml' )
    );

    $html = LegacySectionExtractor::captureSettingsMcsContent();
    if ( $html === '' ) {
      $message = esc_html__( 'Translation Editor settings are unavailable until WPML setup completes.', 'wpml' );
      echo '<p>' . $message . '</p>';
      return;
    }

    $section = LegacySectionExtractor::extractSectionById( $html, self::SECTION_ID );
    if ( $section === '' ) {
      $message = esc_html__( 'Translation Editor section is not available on this site.', 'wpml' );
      echo '<p>' . $message . '</p>';
      return;
    }

    echo $section;
  }


}
