<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;

class TranslatedDocumentsOptionsController implements PageRenderInterface {

  const SECTION_ID = 'ml-content-setup-sec-3';

  const PAGE_URL_HEADING = 'Page URL';


  public function render() {
    wp_enqueue_script( 'wpml-settings-flash' );

    SettingsPageChrome::printTitle(
      __( 'Translated Documents Options', 'wpml' ),
      __( 'Control the publish state of translations and what appears in the Translation Editor.', 'wpml' )
    );

    $html = LegacySectionExtractor::captureSettingsMcsContent();
    if ( $html === '' ) {
      $message = esc_html__( 'Translated documents options are unavailable until WPML setup completes.', 'wpml' );
      echo '<p>' . $message . '</p>';
      return;
    }

    $section = LegacySectionExtractor::extractSectionByIdWithoutInner(
      $html,
      self::SECTION_ID,
      self::PAGE_URL_HEADING
    );
    if ( $section === '' ) {
      $message = esc_html__( 'Translated documents section is not available on this site.', 'wpml' );
      echo '<p>' . $message . '</p>';
      return;
    }

    $section = self::replaceTranslatedTaxonomiesParagraph( $section );

    echo $section;
  }


  private static function replaceTranslatedTaxonomiesParagraph( string $section ): string {
    $needle = '<p id="tm_block_retranslating_terms">';
    $pos    = strpos( $section, $needle );
    if ( $pos === false ) {
      return $section;
    }

    $closePos = strpos( $section, '</p>', $pos );
    if ( $closePos === false ) {
      return $section;
    }

    $endAt       = $closePos + strlen( '</p>' );
    $replacement = TranslatedTaxonomiesToggle::getInlineMarkup(
      admin_url( 'admin.php?page=tm/menu/settings&section=taxonomies' ),
      /* translators: Name of the Taxonomies Translation section of WPML → Settings: its entry in the settings search list, and link text pointing at it. */
      __( 'Taxonomies Translation', 'wpml' )
    );

    return substr( $section, 0, $pos ) . $replacement . substr( $section, $endAt );
  }


}
