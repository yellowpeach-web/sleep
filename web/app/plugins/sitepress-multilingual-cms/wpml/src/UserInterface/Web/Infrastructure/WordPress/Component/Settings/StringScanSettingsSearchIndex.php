<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;

use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations\AdminTextsTranslationController;

class StringScanSettingsSearchIndex implements \IWPML_Backend_Action {

  const STRING_TRANSLATION_SECTION = 'section=string-translation';


  public function add_hooks() {
    add_filter( 'wpml_settings_search_index', array( $this, 'addSubs' ) );
  }


  public function addSubs( $index ) {
    if (
      ! is_array( $index )
      || ! current_user_can( 'manage_options' )
      || ! AdminTextsTranslationController::rendersLocalizationSection()
    ) {
      return $index;
    }

    foreach ( $index as $i => $section ) {
      if (
        is_array( $section )
        && isset( $section['href'] )
        && is_string( $section['href'] )
        && strpos( $section['href'], self::STRING_TRANSLATION_SECTION ) !== false
      ) {
        $subs                = isset( $section['subs'] ) && is_array( $section['subs'] ) ? $section['subs'] : array();
        $subs[]              = self::scanEntry();
        $index[ $i ]['subs'] = $subs;
        break;
      }
    }

    return $index;
  }


  private static function scanEntry(): array {
    return array(
      /* translators: Entry in the settings search list of WPML → Settings. It opens the section of the Admin Texts Translation page that scans the theme, the plugins and WordPress for texts to translate. */
      'label'    => __( 'Scan themes and plugins for strings', 'wpml' ),
      'anchor'   => AdminTextsTranslationController::LOCALIZATION_SECTION_ID,
      'href'     => AdminTextsTranslationController::localizationSectionUrl(),
      'keywords' => array(
        /* translators: Search words for the scan of themes and plugins in the settings search of WPML → Settings. Not shown on screen; give the words a user of your language would type. */
        __( 'scan strings', 'wpml' ),
        /* translators: Search words for the scan of themes and plugins in the settings search of WPML → Settings. Not shown on screen; give the words a user of your language would type. */
        __( 'scan plugin', 'wpml' ),
        /* translators: Search words for the scan of themes and plugins in the settings search of WPML → Settings. Not shown on screen; give the words a user of your language would type. */
        __( 'string scan', 'wpml' ),
        /* translators: Search words for the scan of themes and plugins in the settings search of WPML → Settings. Not shown on screen; give the words a user of your language would type. */
        __( 'theme strings', 'wpml' ),
        /* translators: Search words for the scan of themes and plugins in the settings search of WPML → Settings. Not shown on screen; give the words a user of your language would type. */
        __( 'plugin strings', 'wpml' ),
        /* translators: Search words for the scan of themes and plugins in the settings search of WPML → Settings. Not shown on screen; give the words a user of your language would type. */
        __( 'WordPress strings', 'wpml' ),
        /* translators: Search words for the scan of themes and plugins in the settings search of WPML → Settings. Not shown on screen; give the words a user of your language would type. */
        __( 'strings in themes and plugins', 'wpml' ),
        /* translators: Search words for the scan of themes and plugins in the settings search of WPML → Settings. Not shown on screen; give the words a user of your language would type. */
        __( 'register strings', 'wpml' ),
        /* translators: Search words for the scan of themes and plugins in the settings search of WPML → Settings. Not shown on screen; give the words a user of your language would type. */
        __( 'localization', 'wpml' ),
      ),
    );
  }


}
