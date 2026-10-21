<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;

use WPML\ST\StringsScanning\JS\SettingsHooks as JSScanSettingsHooks;
use WPML\StringTranslation\Infrastructure\Setting\Repository\SettingsRepository;
use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;

class StringTranslationController implements PageRenderInterface {


  public function render() {
    wp_enqueue_script( 'wpml-settings-flash' );

    $stringsUrl  = admin_url( 'admin.php?page=tm/menu/main.php&tab=strings' );
    $stringsLink = '<a href="' . esc_url( $stringsUrl ) . '">'
      . esc_html__( 'WPML → Translations → String Translation', 'wpml' )
      . '</a>';
    $subtitle = sprintf(
      /* translators: %s is a link to the String Translation tab under WPML → Translations. */
      __( 'Site-wide preferences for how WPML discovers translatable strings. The full list of strings to translate is available under %s.', 'wpml' ),
      $stringsLink
    );

    SettingsPageChrome::printTitle(
      /* translators: Name of the String Translation section of WPML → Settings: its entry in the settings search list and its heading. */
      __( 'String Translation', 'wpml' ),
      '',
      $subtitle
    );

    if ( ! class_exists( JSScanSettingsHooks::class ) || ! class_exists( SettingsRepository::class ) ) {
      $message = \wpml_bold_names(
        __( 'String Translation settings require WPML String Translation to be active.', 'wpml' )
      );
      echo '<p>' . $message . '</p>';
      return;
    }

    $sitepress  = $GLOBALS['sitepress'] ?? null;
    $stSettings = $sitepress ? (array) $sitepress->get_setting( 'st' ) : array();
    $enabled    = ! empty( $stSettings[ SettingsRepository::DETECT_JS_STRINGS ] );

    $hooks = new JSScanSettingsHooks( $enabled );
    $hooks->insertSettingSection();
  }


}
