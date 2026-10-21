<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings\UrlsAndSeo\PageUrl;

class NonLatinSlugNote {

  const ELEMENT_ID = 'wpml-non-latin-slug-note';

  private const EXAMPLE_ROW_CLASS = 'wpml:flex wpml:flex-wrap wpml:items-center wpml:gap-2';



  public static function render( string $savedPageUrl ): string {
    return $savedPageUrl === 'translate' ? self::html() : '';
  }


  public static function html(): string {
    $languages = self::displaySet();
    if ( $languages === array() ) {
      return '';
    }

    $labels = array_column( $languages, 'label' );

    $wrapper = 'wpml:mt-4 wpml:rounded-md wpml:border-l-4 wpml:border-blue wpml:bg-blue-lightest wpml:p-4';

    $html  = '<div id="' . esc_attr( self::ELEMENT_ID ) . '" class="' . esc_attr( $wrapper ) . '">';
    $html .= '<h4 class="wpml:mt-0 wpml:text-sm wpml:font-semibold wpml:text-gray-900 wpml:mb-1">' . self::heading( $labels ) . '</h4>';
    $html .= '<p class="wpml:text-gray-700 wpml:mb-2">' . self::intro( $labels ) . '</p>';
    $html .= self::examples( $languages );
    $html .= self::benefits();
    $html .= '</div>';

    return $html;
  }


  private static function displaySet(): array {
    $sitepress = $GLOBALS['sitepress'] ?? null;
    if ( ! $sitepress ) {
      return array();
    }

    $active  = $sitepress->get_active_languages();
    $hidden  = (array) $sitepress->get_setting( 'hidden_languages', array() );
    $default = $sitepress->get_default_language();
    $order   = (array) $sitepress->get_setting( 'languages_order', array() );

    $activeCodes = array_keys( $active );
    $ordered     = array_intersect( $order, $activeCodes );
    $codes       = array_merge( $ordered, array_diff( $activeCodes, $ordered ) );

    $result = array();
    foreach ( $codes as $code ) {
      $code = (string) $code;
      if ( $code === $default || in_array( $code, $hidden, true ) ) {
        continue;
      }
      $example = TransliterationExamples::get( $code );
      if ( $example === null ) {
        continue;
      }
      $result[ $code ] = $example;
    }

    return $result;
  }


  private static function heading( array $labels ): string {
    $names = count( $labels ) === 1
      ? $labels[0]
      : wp_sprintf_l( '%l', $labels );

    /* translators: %s is one language name or a list of them, e.g. "Hebrew and Chinese". */
    return sprintf( esc_html__( '%s slugs will use Latin letters', 'wpml' ), esc_html( $names ) );
  }


  private static function intro( array $labels ): string {
    if ( count( $labels ) === 1 ) {
      /* translators: %s is a language name, e.g. "Hebrew". Appears three times. */
      return sprintf(
        /* translators: Note on WPML → Settings about web addresses in languages that do not use the Latin alphabet. "This" is writing the slug in Latin letters. %1$s: the name of the language, for example Hebrew; it appears three times. */
        esc_html__(
          'URLs cannot reliably show %1$s characters. When you translate a slug into %1$s, WPML will write it in Latin letters that match how the %1$s reads. This is called transliteration.',
          'wpml'
        ),
        esc_html( $labels[0] )
      );
    }

    return esc_html__(
      'URLs cannot reliably show characters from these languages. When you translate a slug, WPML will write it in Latin letters that match how each language reads. This is called transliteration.',
      'wpml'
    );
  }


  private static function examples( array $languages ): string {
    $rows  = '';
    $first = true;
    foreach ( $languages as $lang ) {
      /* translators: Row label in a worked example on WPML → Settings, such as "Hebrew title": the title of a page in that language. %s: the name of a language. */
      $rowLabel = sprintf( esc_html__( '%s title', 'wpml' ), esc_html( $lang['label'] ) );
      $rowClass = $first ? '' : 'wpml:mt-3 wpml:pt-3 wpml:border-t wpml:border-gray-100';
      $first    = false;

      $rows .= '<div class="' . esc_attr( $rowClass ) . '">';
      $rows .= '<div class="' . self::EXAMPLE_ROW_CLASS . '">';
      $rows .= '<span class="wpml:text-gray-500">' . $rowLabel . '</span>';
      $rows .= '<span class="wpml:font-mono wpml:text-gray-800" dir="auto">' . esc_html( $lang['nativeTitle'] ) . '</span>';
      $rows .= '</div>';
      $rows .= '<div class="' . self::EXAMPLE_ROW_CLASS . ' wpml:mt-1">';
      /* translators: Name of the Page URL section of WPML → Settings: its entry in the settings search list, its heading, and a row label in the worked example under it. */
      $rows .= '<span class="wpml:text-gray-500">' . esc_html__( 'Page URL', 'wpml' ) . '</span>';
      $rows .= '<span class="wpml:font-mono wpml:text-gray-800">' . esc_html( $lang['pageUrl'] ) . '</span>';
      $rows .= '</div>';
      $rows .= '</div>';
    }

    return '<div class="wpml:bg-white wpml:border wpml:border-gray-200 wpml:rounded wpml:p-3 wpml:mb-2 wpml:text-xs">' . $rows . '</div>';
  }


  private static function benefits(): string {
    $lines = array(
      esc_html__( 'Visitors get a short link they can read, copy, and share.', 'wpml' ),
      esc_html__( 'Search engines index one clean, unique URL for each language.', 'wpml' ),
    );

    $html  = '';
    $first = true;
    foreach ( $lines as $line ) {
      $spacing = $first ? '' : ' wpml:mt-1';
      $first   = false;
      $html   .= '<p class="wpml:mb-0 wpml:flex wpml:items-start wpml:gap-2 wpml:text-gray-700' . $spacing . '">';
      $html   .= self::checkIcon();
      $html   .= '<span>' . $line . '</span>';
      $html   .= '</p>';
    }

    return $html;
  }


  private static function checkIcon(): string {
    return '<svg class="wpml:w-4 wpml:h-4 wpml:text-green-600 wpml:shrink-0 wpml:mt-1" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>';
  }



}
