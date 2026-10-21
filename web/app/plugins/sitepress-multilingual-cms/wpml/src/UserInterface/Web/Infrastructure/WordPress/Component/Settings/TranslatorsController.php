<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;
use WPML_TM_Menus_Settings;
use WPML_TM_Translation_Roles_Section_Factory;

class TranslatorsController implements PageRenderInterface {

  const PICKUP_MODE_SECTION_ID = 'ml-content-setup-sec-5';
  const XLIFF_PARTIAL          = '/menu/xliff-options.php';


  public function render() {
    wp_enqueue_script( 'wpml-settings-flash' );

    SettingsPageChrome::printTitle(
      /* translators: Name of the Translators section of WPML → Settings: its entry in the settings search list and its heading. The people who translate the site. */
      __( 'Translators', 'wpml' ),
      __( 'Manage the people who translate your site — your own local translators, Translation Managers who oversee the process, and notifications. Professional translation-service integrations are available below.', 'wpml' )
    );

    if ( class_exists( WPML_TM_Translation_Roles_Section_Factory::class ) ) {
      $section = ( new WPML_TM_Translation_Roles_Section_Factory() )->create();
      $section->render();
    } else {
      $message = esc_html__( 'Translators settings are unavailable until WPML setup completes.', 'wpml' );
      echo '<p>' . $message . '</p>';
    }

    if ( class_exists( WPML_TM_Menus_Settings::class ) ) {
      $page = new WPML_TM_Menus_Settings();
      $page->init();
      $page->build_content_translation_notifications();
    }

    $this->renderTranslationPickupMode();
    $this->renderXliffOptions();
  }


  private function renderTranslationPickupMode(): void {
    $html = LegacySectionExtractor::captureSettingsMcsContent();
    if ( $html === '' ) {
      return;
    }
    $section = LegacySectionExtractor::extractSectionById( $html, self::PICKUP_MODE_SECTION_ID );
    if ( $section === '' ) {
      return;
    }
    $section = LegacySectionExtractor::promoteSectionHeadingsToH2( $section );
    echo $section;
  }


  private function renderXliffOptions(): void {
    $partial = ICL_PLUGIN_PATH . self::XLIFF_PARTIAL;
    $html    = LegacySectionExtractor::captureLegacyPartial( $partial );
    if ( $html === '' ) {
      return;
    }
    $html = LegacySectionExtractor::promoteSectionHeadingsToH2( $html );
    echo $html;
  }


}
