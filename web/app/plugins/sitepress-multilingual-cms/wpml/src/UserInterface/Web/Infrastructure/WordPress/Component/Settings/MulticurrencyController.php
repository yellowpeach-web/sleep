<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Wcml\WcmlEmbed;

class MulticurrencyController implements PageRenderInterface {


  public function render() {
    SettingsPageChrome::printTitle(
      /* translators: Name of the Multicurrency section of WPML → Settings: its entry in the settings search list and its heading. Selling in more than one currency. */
      __( 'Multicurrency', 'wpml' ),
      __( 'Sell in multiple currencies. Configure which currencies are available, exchange rates, and how the currency switcher renders for visitors.', 'wpml' )
    );
    WcmlSettingsController::printSectionCardStyle();
    echo '<div class="wpml-wcml-embed">';
    WcmlEmbed::embed( 'multi-currency' );
    echo '</div>';
  }


}
