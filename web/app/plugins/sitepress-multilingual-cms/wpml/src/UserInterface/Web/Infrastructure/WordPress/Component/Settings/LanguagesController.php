<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;

use WPML\Core\SharedKernel\Component\Language\Application\Query\Dto\LanguageDto;
use WPML\Core\SharedKernel\Component\Language\Application\Query\LanguagesQueryInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\LanguageEditor\LanguageEditorEmbed;

class LanguagesController implements PageRenderInterface {

  const LEGACY_PARTIAL = '/menu/languages.php';

  const SECTION_IDS = array(
    'lang-sec-1',
    'lang-sec-7',
  );

  private $languagesQuery;

  private $languagesQueryWithAutomaticSupport;


  public function __construct(
    LanguagesQueryInterface $languagesQuery,
    LanguagesQueryInterface $languagesQueryWithAutomaticSupport
  ) {
    $this->languagesQuery                     = $languagesQuery;
    $this->languagesQueryWithAutomaticSupport = $languagesQueryWithAutomaticSupport;
    $section = isset( $_GET['section'] ) && is_string( $_GET['section'] ) ? $_GET['section'] : '';
    if ( $section !== 'languages' ) {
      return;
    }
    add_action(
      'admin_enqueue_scripts',
      static function () {
        if ( class_exists( \WPML\Languages\UI::class ) ) {
          ( new \WPML\Languages\UI() )->add_hooks();
        }
      },
      1
    );
  }


  public function render() {
    wp_enqueue_script( 'wpml-settings-flash' );

    $description = __( "Choose which languages your site supports, hide languages that are still in progress, and control how they're mapped to translation engines.", 'wpml' );
    /* translators: Name of the Languages section of WPML → Settings: its entry in the settings search list and its heading. */
    SettingsPageChrome::printTitle( __( 'Languages', 'wpml' ), $description );

    $this->enqueueTeaCalculator();
    $this->renderSharedEditor();

    $html = LegacySectionExtractor::captureLegacyPartial( ICL_PLUGIN_PATH . self::LEGACY_PARTIAL );
    if ( $html === '' ) {
      $message = esc_html__( 'Languages settings are unavailable until WPML setup completes.', 'wpml' );
      echo '<p>' . $message . '</p>';
      return;
    }

    $sections = LegacySectionExtractor::extractMultipleSectionsByIds(
      $html,
      self::SECTION_IDS
    );
    if ( $sections === '' ) {
      $message = esc_html__( 'No Languages sections are available on this site.', 'wpml' );
      echo '<p>' . $message . '</p>';
      return;
    }

    echo LegacySectionExtractor::dissolveHiddenCollision( $sections );
  }


  private function enqueueTeaCalculator(): void {
    wp_enqueue_script( 'wpml-settings-tea' );
    if ( wp_style_is( 'wpml-setup-tea', 'registered' ) ) {
      wp_enqueue_style( 'wpml-setup-tea' );
    }
    if ( wp_style_is( 'wpml-settings-tea', 'registered' ) ) {
      wp_enqueue_style( 'wpml-settings-tea' );
    }

    $data = wp_json_encode(
      [
        'languages'       => $this->getLanguagesPayload(),
        'defaultLanguage' => $this->languagesQuery->getDefaultCode(),
      ]
    );
    wp_add_inline_script(
      'wpml-settings-tea',
      'window.wpmlLanguageEditorTeaData = ' . ( $data ?: '{}' ) . ';',
      'before'
    );
  }


  private function getLanguagesPayload(): array {
    $languages = $this->languagesQueryWithAutomaticSupport->getActive();

    return array_map(
      function ( LanguageDto $language ) {
        return [
          'code'                             => $language->getCode(),
          'name'                             => $language->getEnglishName(),
          'flagUrl'                          => $language->getCountryFlagUrl() ?: '',
          'homeUrl'                          => '',
          'doesSupportAutomaticTranslations' => $language->doesSupportAutomaticTranslations() !== false,
          'excludedReason'                   => $language->getAutomaticTranslationsUnavailableReason(),
          'translationPaused'                => $language->isTranslationPaused(),
          'creditsPerWord'                   => false,
        ];
      },
      array_values( $languages )
    );
  }


  private function renderSharedEditor(): void {
    if ( ! class_exists( \WPML\LanguageEditor\PageData::class ) ) {
      return;
    }

    LanguageEditorEmbed::enqueue();

    echo '<div class="wpml-language-editor-host wpml:mb-8">';
    LanguageEditorEmbed::renderHost(
      LanguageEditorEmbed::SURFACE_SETTINGS,
      array(
        'show-code-fields' => 'true',
        'default-code'     => \WPML\LanguageEditor\PageData::defaultCode(),
      )
    );
    echo '</div>';
  }


}
