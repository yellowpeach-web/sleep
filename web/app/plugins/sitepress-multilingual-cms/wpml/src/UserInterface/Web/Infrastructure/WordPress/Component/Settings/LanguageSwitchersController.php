<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;

class LanguageSwitchersController implements PageRenderInterface {

  const LEGACY_PARTIAL = '/menu/languages.php';

  const FORM_SECTION_IDS = array(
    'wpml-language-switcher-options',
    'wpml-language-switcher-menus',
    'wpml-language-switcher-sidebars',
    'wpml-language-switcher-footer',
    'wpml-language-switcher-post-translations',
    'wpml-language-switcher-shortcode-action',
  );

  const STANDALONE_SECTION_ID = 'lang-sec-2-1';


  public function render() {
    wp_enqueue_script( 'wpml-settings-flash' );

    SettingsPageChrome::printTitle(
      /* translators: Name of the Language Switchers section of WPML → Settings: its entry in the settings search list and its heading. */
      __( 'Language Switchers', 'wpml' ),
      __( 'All language switchers on your site share these options and behaviors.', 'wpml' )
    );

    $html = LegacySectionExtractor::captureLegacyPartial( ICL_PLUGIN_PATH . self::LEGACY_PARTIAL );
    if ( $html === '' ) {
      $message = esc_html__( 'Language Switcher settings are unavailable until WPML setup completes.', 'wpml' );
      echo '<p>' . $message . '</p>';
      return;
    }

    $formSections = LegacySectionExtractor::extractMultipleSectionsByIds( $html, self::FORM_SECTION_IDS );
    $flagFormat   = LegacySectionExtractor::extractSectionById( $html, self::STANDALONE_SECTION_ID );

    if ( $formSections === '' && $flagFormat === '' ) {
      $message = esc_html__( 'No Language Switcher sections are available on this site.', 'wpml' );
      echo '<p>' . $message . '</p>';
      return;
    }

    $out = '';
    if ( $formSections !== '' ) {
      $out .= '<form id="wpml-ls-settings-form" name="wpml_ls_settings_form">'
        . '<input type="hidden" name="wpml-ls-refresh-on-browser-back-button"'
        . ' id="wpml-ls-refresh-on-browser-back-button" value="no">'
        . $formSections
        . self::renderDialogBox()
        . '</form>';
    }
    $out .= $flagFormat;

    $out = LegacySectionExtractor::promoteSectionHeadingsToH2( $out );

    echo LegacySectionExtractor::dissolveHiddenCollision( $out );
  }


  private static function renderDialogBox(): string {
    $cancel = esc_attr__( 'Cancel', 'sitepress' );
    $save   = esc_attr__( 'Save', 'sitepress' );

    return '<div id="wpml-ls-dialog" style="display:none;">'
      . '<div class="js-wpml-ls-dialog-inner"></div>'
      . '<div class="wpml-dialog-footer">'
      . '<span class="errors icl_error_text"></span>'
      . '<input class="js-wpml-ls-dialog-close cancel wpml-dialog-close-button alignleft'
      . ' wpml-button base-btn gray-light-btn" value="' . $cancel . '" type="button">'
      . '<input class="button-primary js-wpml-ls-dialog-save wpml-button base-btn term-save alignright"'
      . ' value="' . $save . '" type="submit">'
      . '<span class="spinner alignright"></span>'
      . '</div>'
      . '</div>';
  }


}
