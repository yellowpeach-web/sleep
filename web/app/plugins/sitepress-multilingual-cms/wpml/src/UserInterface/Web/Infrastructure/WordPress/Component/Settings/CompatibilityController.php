<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;
use WPML_ST_Theme_Plugin_Localization_Options_UI;
use WPML_Theme_Plugin_Localization_UI_Hooks;
use function WPML\Container\make;

class CompatibilityController implements PageRenderInterface {

  const LEGACY_PARTIAL    = '/menu/theme-localization.php';
  const LANGUAGES_PARTIAL = '/menu/languages.php';
  const LOGIN_PARTIAL     = '/menu/_login_translation_options.php';

  const LANGUAGES_SECTION_IDS = array(
    'lang-sec-8',
    'cookie',
  );


  public function render() {
    wp_enqueue_script( 'wpml-settings-flash' );

    SettingsPageChrome::printTitle(
      /* translators: Name of the Compatibility section of WPML → Settings: its entry in the settings search list and its heading. */
      __( 'Compatibility', 'wpml' ),
      __( "Fallbacks and helpers for themes, plugins and server setups that don't fully support multilingual out of the box.", 'wpml' )
    );

    $callable = array( WPML_Theme_Plugin_Localization_UI_Hooks::class, 'render_options_ui' );
    if ( ! has_action( 'wpml_custom_localization_type', $callable ) ) {
      $hooks = make( WPML_Theme_Plugin_Localization_UI_Hooks::class );
      $hooks->add_hooks();
      $hooks->enqueue_styles();
    }

    if ( class_exists( WPML_ST_Theme_Plugin_Localization_Options_UI::class ) ) {
      $sitepress = $GLOBALS['sitepress'] ?? null;
      if ( $sitepress ) {
        $stSettings = $sitepress->get_setting( 'st' );
        $stHooks    = new WPML_ST_Theme_Plugin_Localization_Options_UI(
          is_array( $stSettings ) ? $stSettings : array()
        );
        $stHooks->add_hooks();
      }
    }

    $html = LegacySectionExtractor::captureLegacyPartial( ICL_PLUGIN_PATH . self::LEGACY_PARTIAL );
    if ( $html === '' ) {
      $message = esc_html__( 'Theme and plugins localization is unavailable until WPML setup completes.', 'wpml' );
      echo '<p>' . $message . '</p>';
      return;
    }

    $stripped = preg_replace( '#^\s*<div class="wrap">|</div>\s*$#', '', $html );
    if ( ! is_string( $stripped ) || $stripped === '' ) {
      $stripped = $html;
    }

    $stripped = self::injectLocalizationOptionsAnchor( $stripped );

    $stripped = self::stripScanStringsSection( $stripped );

    $stripped = LegacySectionExtractor::promoteSectionHeadingsToH2( $stripped );

    echo $stripped;

    $this->renderLanguagesSections();
    $this->renderLoginRegistrationSection();
  }


  private static function stripScanStringsSection( string $html ): string {
    $openTag = '<details class="wpml-section wpml-st-localization wpml-st-localization-details"'
      . ' id="wpml-st-localization">';
    $start   = strpos( $html, $openTag );
    if ( $start === false ) {
      return $html;
    }

    $closeTag = '</details>';
    $end      = strpos( $html, $closeTag, $start );
    if ( $end === false ) {
      return $html;
    }

    return substr( $html, 0, $start ) . substr( $html, $end + strlen( $closeTag ) );
  }


  private static function injectLocalizationOptionsAnchor( string $html ): string {
    $heading = __( 'Localization options', 'sitepress' );
    $needle  = '<h3>' . $heading . '</h3>';

    $pos = strpos( $html, $needle );
    if ( $pos === false ) {
      return $html;
    }

    $openTag    = '<div class="wpml-section">';
    $sectionPos = strrpos( substr( $html, 0, $pos ), $openTag );
    if ( $sectionPos === false ) {
      return $html;
    }

    return substr( $html, 0, $sectionPos )
      . '<div class="wpml-section" id="localization-options">'
      . substr( $html, $sectionPos + strlen( $openTag ) );
  }


  private function renderLanguagesSections(): void {
    $this->forceEnqueueCookieAdminScripts();
    $cookieHooks = $this->forceRegisterCookieAdminUi();

    $html = LegacySectionExtractor::captureLegacyPartial( ICL_PLUGIN_PATH . self::LANGUAGES_PARTIAL );

    if ( $cookieHooks !== null ) {
      remove_action( 'wpml_after_settings', array( $cookieHooks, 'render_cookie_box' ) );
    }

    if ( $html === '' ) {
      return;
    }
    $sections = LegacySectionExtractor::extractMultipleSectionsByIds( $html, self::LANGUAGES_SECTION_IDS );
    if ( $sections === '' ) {
      return;
    }
    $sections = LegacySectionExtractor::promoteSectionHeadingsToH2( $sections );
    echo $sections;
  }


  private function forceEnqueueCookieAdminScripts(): void {
    if ( ! class_exists( \WPML_Cookie_Admin_Scripts::class ) ) {
      return;
    }

    $scripts = new \WPML_Cookie_Admin_Scripts();
    $scripts->enqueue_scripts();
  }


  private function forceRegisterCookieAdminUi() {
    $sitepress = $GLOBALS['sitepress'] ?? null;
    if ( ! $sitepress || ! $sitepress->get_setting( 'setup_complete' ) ) {
      return null;
    }
    if ( ! class_exists( \WPML_Cookie_Setting::class ) || ! class_exists( \WPML_Cookie_Admin_UI::class ) ) {
      return null;
    }
    if ( ! class_exists( \WPML_Twig_Template_Loader::class ) ) {
      return null;
    }

    $cookieSetting = new \WPML_Cookie_Setting( $sitepress );
    $templatePaths = array( ICL_PLUGIN_PATH . '/templates/cookie-setting' );
    $twigLoader    = new \WPML_Twig_Template_Loader( $templatePaths );
    $cookieAdminUi = new \WPML_Cookie_Admin_UI( $twigLoader->get_template(), $cookieSetting );
    $cookieAdminUi->add_hooks();

    return $cookieAdminUi;
  }


  private function renderLoginRegistrationSection(): void {
    $html = LegacySectionExtractor::captureLegacyPartial( ICL_PLUGIN_PATH . self::LOGIN_PARTIAL );
    if ( $html === '' ) {
      return;
    }
    $html = LegacySectionExtractor::promoteSectionHeadingsToH2( $html );
    echo $html;
  }


}
