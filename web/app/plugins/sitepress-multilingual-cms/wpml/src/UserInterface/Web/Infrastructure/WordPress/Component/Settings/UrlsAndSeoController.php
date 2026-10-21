<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings\UrlsAndSeo\PageUrl\EncodedUrlSlugNote;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings\UrlsAndSeo\PageUrl\NonLatinSlugNote;

class UrlsAndSeoController implements PageRenderInterface {

  const LANGUAGES_PARTIAL = '/menu/languages.php';

  const PAGE_URL_HEADING = 'Page URL';
  const PAGE_URL_PARENT_SECTION = 'ml-content-setup-sec-3';
  const PAGE_URL_WRAPPER_ID = 'page-url';

  const AFTER_SECTIONS_HOOK = 'wpml_admin_after_urls_and_seo_sections';


  public function render() {
    wp_enqueue_script( 'wpml-settings-flash' );
    wp_enqueue_script( 'wpml-tm-mcs' );

    SettingsPageChrome::printTitle(
      __( 'URLs and SEO', 'wpml' ),
      __( 'How translated URLs are built, how search engines are told about them, and whether visitors are redirected by their browser language.', 'wpml' )
    );

    $languagesHtml = LegacySectionExtractor::captureLegacyPartial( ICL_PLUGIN_PATH . self::LANGUAGES_PARTIAL );
    if ( $languagesHtml === '' ) {
      $message = esc_html__( 'URLs and SEO settings are unavailable until WPML setup completes.', 'wpml' );
      echo '<p>' . $message . '</p>';
      return;
    }

    $urlFormat = LegacySectionExtractor::extractSectionById( $languagesHtml, 'lang-sec-2' );
    $urlFormat = LegacySectionExtractor::promoteSectionHeadingsToH2( $urlFormat );
    echo $urlFormat;

    $this->renderPageUrlSection();

    $this->renderSlugTranslationsSection();

    $remaining = LegacySectionExtractor::extractMultipleSectionsByIds(
      $languagesHtml,
      array( 'lang-sec-9-5', 'lang-sec-9' )
    );
    $remaining = LegacySectionExtractor::promoteSectionHeadingsToH2( $remaining );
    echo $remaining;

    do_action( 'wpml_admin_after_urls_and_seo_sections' );
  }


  private function renderPageUrlSection(): void {
    $mcsHtml = LegacySectionExtractor::captureSettingsMcsContent();
    if ( $mcsHtml === '' ) {
      return;
    }

    $inner = LegacySectionExtractor::extractInnerSectionByHeading(
      $mcsHtml,
      self::PAGE_URL_PARENT_SECTION,
      self::PAGE_URL_HEADING
    );
    if ( $inner === '' ) {
      return;
    }

    echo '<div class="wpml-section" id="' . esc_attr( self::PAGE_URL_WRAPPER_ID ) . '">';
    echo '<div class="wpml-section-header"><h2>';
    /* translators: Name of the Page URL section of WPML → Settings: its entry in the settings search list, its heading, and a row label in the worked example under it. */
    echo esc_html__( 'Page URL', 'wpml' );
    echo '</h2></div>';
    echo '<div class="wpml-section-content">';
    echo '<form name="wpml_page_url_options" id="wpml-page-url-options" action="">';
    wp_nonce_field(
      'wpml-translated-document-options-nonce',
      'wpml-translated-document-options-nonce'
    );
    echo $inner;

    wp_enqueue_script( 'wpml-page-url-non-latin-note' );
    $sitepress    = $GLOBALS['sitepress'] ?? null;
    $savedPageUrl = 'auto-generate';
    if ( $sitepress ) {
      $savedPageUrl = (string) $sitepress->get_setting( 'translated_document_page_url', 'auto-generate' );
    }

    $noteHtml = NonLatinSlugNote::html();
    if ( $noteHtml !== '' ) {
      wp_add_inline_script(
        'wpml-page-url-non-latin-note',
        'window.wpmlPageUrlNonLatinNote = ' . (string) wp_json_encode( $noteHtml ) . ';',
        'before'
      );
    }
    echo NonLatinSlugNote::render( $savedPageUrl );

    wp_enqueue_script( 'wpml-page-url-encoded-url-note' );
    $encodedNoteHtml    = EncodedUrlSlugNote::html();
    $hasEncodedLanguage = ( $encodedNoteHtml === '' );
    if ( $encodedNoteHtml !== '' ) {
      wp_add_inline_script(
        'wpml-page-url-encoded-url-note',
        'window.wpmlPageUrlEncodedUrlNote = ' . (string) wp_json_encode( $encodedNoteHtml ) . ';',
        'before'
      );
    }

    echo '<div class="wpml-section-content-inner">';
    echo '<p class="buttons-wrap">';
    echo '<span class="icl_ajx_response" id="icl_ajx_response_page_url"></span>';
    echo '<input id="js-page-url-options-btn" type="button"';
    echo ' class="button-primary wpml-button base-btn"';
    /* translators: Label on the Save button of a settings section. Verb, imperative. */
    echo ' value="' . esc_attr__( 'Save', 'wpml' ) . '" />';
    echo '<span id="wpml_page_url_save" data-has-encoded-language="' . esc_attr( $hasEncodedLanguage ? '1' : '0' ) . '"></span>';
    echo '</p>';
    echo '</div>';
    echo '</form>';
    echo '</div>';
    echo '</div>';
  }


  private function renderSlugTranslationsSection(): void {
    if ( ! defined( 'WPML_ST_PATH' ) ) {
      return;
    }

    $partial = WPML_ST_PATH . '/menu/_slug-translation-options.php';
    $html    = LegacySectionExtractor::captureLegacyPartial( $partial );
    if ( $html === '' ) {
      return;
    }
    $html = LegacySectionExtractor::promoteSectionHeadingsToH2( $html );
    echo $html;
  }


}
