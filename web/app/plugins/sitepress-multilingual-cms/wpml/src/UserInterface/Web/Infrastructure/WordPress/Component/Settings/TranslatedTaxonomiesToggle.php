<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;

class TranslatedTaxonomiesToggle {

  const FLASH_ANCHOR = 'tm_block_retranslating_terms';


  public static function renderCard( string $crossLinkUrl, string $crossLinkLabel ): void {
    echo '<div class="wpml-section" id="tm_block_retranslating_terms_card">';
    echo '<div class="wpml-section-header"><h2>';
    /* translators: Heading of the block on WPML → Settings that lists which categories and tags are translated. */
    echo esc_html__( 'Translated taxonomies', 'wpml' );
    echo '</h2></div>';

    echo '<div class="wpml-section-content">';
    echo '<form action="">';

    wp_nonce_field( 'wpml-translated-document-options-nonce', 'wpml-translated-document-options-nonce' );

    self::renderToggleMarkup();

    $also = sprintf(
      /* translators: Line under the setting on WPML → Settings, pointing at the other screen that carries the same setting. %s: a link whose text is the name of that screen, either "Translated Documents Options" or "Taxonomies Translation". */
      esc_html__( 'Also available in %s.', 'wpml' ),
      '<a href="' . esc_url( self::withFlashAnchor( $crossLinkUrl ) ) . '">'
        . esc_html( $crossLinkLabel ) . '</a>'
    );
    echo '<p class="description">' . wp_kses( $also, array( 'a' => array( 'href' => array() ) ) ) . '</p>';

    echo '<p class="buttons-wrap">';
    echo '<span class="icl_ajx_response" id="icl_ajx_response_tdo"></span> ';
    echo '<input id="js-translated_document-options-btn" type="button" ';
    echo 'class="button-primary wpml-button base-btn" value="';
    /* translators: Label on the Save button of a settings section. Verb, imperative. */
    echo esc_attr__( 'Save', 'wpml' );
    echo '" />';
    echo '</p>';

    echo '</form>';
    echo '</div>';
    echo '</div>';
  }


  public static function getInlineMarkup( string $crossLinkUrl, string $crossLinkLabel ): string {
    ob_start();
    self::renderToggleMarkup();
    $toggle = (string) ob_get_clean();

    $also = sprintf(
      /* translators: Line under the setting on WPML → Settings, pointing at the other screen that carries the same setting. %s: a link whose text is the name of that screen, either "Translated Documents Options" or "Taxonomies Translation". */
      esc_html__( 'Also available in %s.', 'wpml' ),
      '<a href="' . esc_url( self::withFlashAnchor( $crossLinkUrl ) ) . '">'
        . esc_html( $crossLinkLabel ) . '</a>'
    );

    return $toggle
      . '<p class="description" style="margin-top:.25rem;">'
      . wp_kses( $also, array( 'a' => array( 'href' => array() ) ) )
      . '</p>';
  }


  private static function withFlashAnchor( string $url ): string {
    $sep = strpos( $url, '?' ) === false ? '?' : '&';

    return $url . $sep . 'flash=' . rawurlencode( self::FLASH_ANCHOR ) . '#' . self::FLASH_ANCHOR;
  }


  private static function renderToggleMarkup(): void {
    $sitepress    = $GLOBALS['sitepress'] ?? null;
    $currentValue = $sitepress ? $sitepress->get_setting( 'tm_block_retranslating_terms' ) : '';
    $isHidden     = (string) $currentValue === '1';
    $showChecked  = $isHidden ? '' : ' checked="checked"';
    $hideChecked  = $isHidden ? ' checked="checked"' : '';

    echo '<p id="tm_block_retranslating_terms">';
    echo '<label>';
    echo '<input class="wpml-checkbox-native js-wpml-show-translated-tax" type="checkbox"';
    echo $showChecked . ' />';
    echo ' ' . esc_html__( 'Show translated taxonomies in Translation Editor', 'wpml' );
    echo '</label>';
    echo '<input type="checkbox" class="js-wpml-tm-block-retranslating-mirror" ';
    echo 'name="tm_block_retranslating_terms" value="1" style="display:none"';
    echo $hideChecked . ' />';
    echo '</p>';

    echo "<script>(function(){var v=document.currentScript.previousElementSibling;"
      . "if(!v)return;var c=v.querySelector('.js-wpml-show-translated-tax');"
      . "var m=v.querySelector('.js-wpml-tm-block-retranslating-mirror');"
      . "if(!c||!m)return;function s(){m.checked=!c.checked;}"
      . "c.addEventListener('change',s);})();</script>";
  }


}
