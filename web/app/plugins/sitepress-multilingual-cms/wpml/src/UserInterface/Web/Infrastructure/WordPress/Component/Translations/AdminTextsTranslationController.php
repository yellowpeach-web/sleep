<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;

class AdminTextsTranslationController implements PageRenderInterface {

  const PAGE_SLUG = 'wpml-admin-texts-translation';

  const LOCALIZATION_SECTION_ID = 'wpml-st-localization';

  const LEGACY_PARTIAL = '/menu/string-translation-translate-options.php';


  public static function localizationSectionUrl(): string {
    return 'admin.php?page=' . self::PAGE_SLUG . '#' . self::LOCALIZATION_SECTION_ID;
  }


  public static function rendersLocalizationSection(): bool {
    $st = $GLOBALS['WPML_String_Translation'] ?? null;

    return is_object( $st ) && method_exists( $st, 'localization_type_ui' );
  }


  public function render() {
    if ( ! defined( 'WPML_ST_VERSION' ) || ! defined( 'WPML_ST_PATH' ) ) {
      $message = \wpml_bold_names(
        __( 'Admin Texts Translation requires the WPML String Translation plugin.', 'wpml' )
      );
      echo '<div class="wrap"><p>' . $message . '</p></div>';
      return;
    }

    echo '<div class="wrap">';
    echo '<div class="wpml:max-w-5xl wpml:mt-6 wpml:pb-16">';

    self::renderBreadcrumb();

    $partial = WPML_ST_PATH . self::LEGACY_PARTIAL;
    if ( ! is_readable( $partial ) ) {
      echo '<p>';
      echo esc_html__( 'Admin Texts Translation is unavailable.', 'wpml' );
      echo '</p>';
      echo '</div></div>';
      return;
    }

    self::renderTitleAndDescription();

    ob_start();
    include $partial;
    $html = (string) ob_get_clean();

    $stripped = preg_replace( '#^\s*<div class="wrap">|</div>\s*$#', '', $html );
    if ( ! is_string( $stripped ) || $stripped === '' ) {
      $stripped = $html;
    }

    $stripped = preg_replace(
      '#<h2>\s*' . preg_quote( __( 'Admin Texts Translation', 'wpml-string-translation' ), '#' ) . '\s*</h2>#',
      '',
      $stripped
    );

    $stripped = preg_replace(
      '#<p>\s*<a href="[^"]*"[^>]*>\s*&laquo;[^<]*</a>\s*</p>#',
      '',
      (string) $stripped
    );

    echo $stripped;

    self::renderScanStringsCollapsible();

    echo '</div></div>';
  }


  private static function renderTitleAndDescription(): void {
    $stringsUrl = admin_url( 'admin.php?page=tm/menu/main.php&tab=strings' );

    echo '<h1 style="font-size:1.5rem;font-weight:600;color:#111827;margin:0 0 .25em 0;">';
    echo esc_html__( 'Admin Texts Translation', 'wpml' );
    echo '</h1>';

    $stringsLink = '<a href="' . esc_url( $stringsUrl ) . '">'
      /* translators: Name of the Strings tab of WPML → Translations: the texts of the theme, the plugins and the site. Also used as link text and as a back-link to that tab. */
      . esc_html__( 'Strings', 'wpml' )
      . '</a>';

    echo '<p style="font-size:14px;color:#6b7280;margin:0 0 1em 0;">';
    printf(
      /* translators: %s is a link to the Strings tab. */
      esc_html__( 'Select admin texts (site title, tagline, widgets, and options registered by themes and plugins) to make them translatable. Marked items appear in the %s screen.', 'wpml' ),
      $stringsLink
    );
    echo '</p>';
  }


  private static function renderBreadcrumb(): void {
    $stringsUrl = admin_url( 'admin.php?page=tm/menu/main.php&tab=strings' );

    echo '<p class="wpml-admin-texts-back" style="margin:0 0 1em 0;font-size:13px;">';
    echo '<a href="' . esc_url( $stringsUrl ) . '">';
    /* translators: Name of the Strings tab of WPML → Translations: the texts of the theme, the plugins and the site. Also used as link text and as a back-link to that tab. */
    echo '&larr; ' . esc_html__( 'Strings', 'wpml' );
    echo '</a></p>';
  }


  private static function renderScanStringsCollapsible(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
      return;
    }

    $st = $GLOBALS['WPML_String_Translation'] ?? null;
    if ( ! is_object( $st ) || ! method_exists( $st, 'localization_type_ui' ) ) {
      return;
    }

    ob_start();
    $st->localization_type_ui();
    $scanHtml = (string) ob_get_clean();

    if ( trim( $scanHtml ) === '' ) {
      return;
    }

    echo '<style>'
      . '#wpml-st-localization{margin-top:1.5em;}'
      . '#wpml-st-localization .wpml-section-content-wide{margin-left:0!important;width:auto!important;}'
      . '</style>';

    echo $scanHtml;
  }


}
