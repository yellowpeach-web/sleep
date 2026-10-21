<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings\UrlsAndSeo\PageUrl;

class EncodedUrlSlugNote {

  const ELEMENT_ID = 'wpml-encoded-url-slug-note';


  public static function activeEncodedLanguageCodes(): array {
    $sitepress = $GLOBALS['sitepress'] ?? null;
    if ( ! $sitepress ) {
      return array();
    }

    $codes = array();
    foreach ( (array) $sitepress->get_languages( false, true ) as $code => $language ) {
      if ( is_array( $language ) && ! empty( $language['encode_url'] ) ) {
        $codes[] = (string) ( $language['code'] ?? $code );
      }
    }

    return $codes;
  }


  public static function html(): string {
    if ( empty( $GLOBALS['sitepress'] ) || self::activeEncodedLanguageCodes() !== array() ) {
      return '';
    }

    $wrapper  = 'wpml:mt-4 wpml:rounded-md wpml:border-l-4 wpml:border-blue wpml:bg-blue-lightest wpml:p-4';
    $sentence = sprintf(
      /* translators: Note under the Page URL option "Copy the original slug for languages with encoded URLs", shown when no active language has "Encode URL" turned on. "Encode URL" is the name of a field in the Languages editor; use the same words as that field. %1$s and %2$s are the start and end of a link to WPML → Settings → Languages. */
      esc_html__( 'This option has no effect right now. None of your active languages has "Encode URL" turned on. You can change this in %1$sLanguages%2$s.', 'wpml' ),
      '<a href="' . esc_url( admin_url( 'admin.php?page=tm/menu/settings&section=languages' ) ) . '">',
      '</a>'
    );

    $html  = '<div id="' . esc_attr( self::ELEMENT_ID ) . '" class="' . esc_attr( $wrapper ) . '">';
    $html .= '<p class="wpml:mt-0 wpml:mb-0 wpml:text-gray-700">' . wp_kses( $sentence, array( 'a' => array( 'href' => array() ) ) ) . '</p>';
    $html .= '</div>';

    return $html;
  }


}
