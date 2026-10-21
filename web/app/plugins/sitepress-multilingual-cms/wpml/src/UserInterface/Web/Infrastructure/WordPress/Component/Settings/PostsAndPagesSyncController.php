<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;

class PostsAndPagesSyncController implements PageRenderInterface {

  const LEGACY_PARTIAL = '/menu/_posts_sync_options.php';


  public function render() {
    wp_enqueue_script( 'wpml-settings-flash' );

    SettingsPageChrome::printTitle(
      __( 'Posts and Pages Synchronization', 'wpml' ),
      __( 'Choose which parts of a post are kept in sync between the original and its translations.', 'wpml' )
    );

    $html = LegacySectionExtractor::captureLegacyPartial( ICL_PLUGIN_PATH . self::LEGACY_PARTIAL );
    if ( $html === '' ) {
      $message = esc_html__(
        'Posts and Pages synchronization settings are unavailable until WPML setup completes.',
        'wpml'
      );
      echo '<p>' . $message . '</p>';
      return;
    }

    echo $html;
  }


}
