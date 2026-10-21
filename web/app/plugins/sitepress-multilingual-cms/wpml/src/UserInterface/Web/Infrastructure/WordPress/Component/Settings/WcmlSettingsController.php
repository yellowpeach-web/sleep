<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Wcml\WcmlEmbed;

class WcmlSettingsController implements PageRenderInterface {


  public function render() {
    SettingsPageChrome::printTitle(
      /* translators: Name of the WooCommerce Multilingual section of WPML → Settings: its entry in the settings search list and its heading. WCML is the product's short name and stays as it is. */
      __( 'WCML', 'wpml' ),
      __( 'WooCommerce Multilingual & Multicurrency. Settings that control how WooCommerce products, media, downloads, reviews and cart behave across languages.', 'wpml' )
    );
    self::printSectionCardStyle();
    echo '<div class="wpml-wcml-embed">';
    WcmlEmbed::embed( 'settings' );
    echo '</div>';
  }


  public static function printSectionCardStyle(): void {
    echo '<style>'
      . '.wcml-section{'
      . 'background:#fff!important;'
      . 'border:1px solid #e5e7eb!important;'
      . 'border-bottom:1px solid #e5e7eb!important;'
      . 'border-radius:6px!important;'
      . 'padding:20px!important;'
      . 'margin:0 0 16px!important'
      . '}'
      . '.wcml-section-header,'
      . '.wcml-section-content,'
      . '.wcml-section-content-wide{'
      . 'float:none!important;'
      . 'width:auto!important;'
      . 'max-width:none!important;'
      . 'margin-left:0!important'
      . '}'
      . '.wpml-wcml-embed input[type="checkbox"]{'
      . 'width:16px;height:16px;'
      . 'appearance:none;-webkit-appearance:none;'
      . 'border:1px solid rgba(47,125,146,.5)!important;'
      . 'border-radius:2px;'
      . 'background-color:transparent!important;'
      . 'background-image:none!important;'
      . 'box-shadow:none!important;'
      . 'position:relative;cursor:pointer'
      . '}'
      . '.wpml-wcml-embed input[type="checkbox"]:checked::before{'
      . 'content:"";width:5px;height:10px;'
      . 'border:solid #2F7D92;border-width:0 2px 2px 0;'
      . 'position:absolute;top:3px;left:8px;'
      . 'transform:rotate(45deg)'
      . '}'
      . '.wpml-wcml-embed input[type="checkbox"]:hover,'
      . '.wpml-wcml-embed input[type="checkbox"]:focus,'
      . '.wpml-wcml-embed input[type="checkbox"]:checked:focus{'
      . 'border-color:#2F7D92!important;box-shadow:none!important'
      . '}'
      . '.wpml-wcml-embed input[type="radio"]{'
      . 'width:16px;height:16px;'
      . 'appearance:none;-webkit-appearance:none;'
      . 'border:4px solid #fff!important;'
      . 'background-color:#fff!important;'
      . 'background-image:none!important;'
      . 'border-radius:50%;'
      . 'box-shadow:0 0 0 1px rgba(47,125,146,.5)!important;'
      . 'cursor:pointer'
      . '}'
      . '.wpml-wcml-embed input[type="radio"]:checked{'
      . 'background-color:#2F7D92!important;'
      . 'box-shadow:0 0 0 1px rgba(47,125,146,.5)!important'
      . '}'
      . '.wpml-wcml-embed input[type="radio"]:hover,'
      . '.wpml-wcml-embed input[type="radio"]:focus{'
      . 'box-shadow:0 0 0 1px #2F7D92!important'
      . '}'
      . '.wpml-wcml-embed input[type="radio"]::before{'
      . 'content:none!important;display:none!important'
      . '}'
      . '.wpml-wcml-embed .button-primary,'
      . '.wpml-wcml-embed input[type="submit"].button-primary{'
      . 'background-color:#2F7D92!important;'
      . 'border-color:#2F7D92!important;'
      . 'color:#fff!important;'
      . 'text-shadow:none!important;'
      . 'box-shadow:none!important'
      . '}'
      . '.wpml-wcml-embed .button-primary:hover,'
      . '.wpml-wcml-embed .button-primary:focus,'
      . '.wpml-wcml-embed input[type="submit"].button-primary:hover,'
      . '.wpml-wcml-embed input[type="submit"].button-primary:focus{'
      . 'background-color:#373737!important;'
      . 'border-color:#373737!important'
      . '}'
      . '</style>';
  }


}
