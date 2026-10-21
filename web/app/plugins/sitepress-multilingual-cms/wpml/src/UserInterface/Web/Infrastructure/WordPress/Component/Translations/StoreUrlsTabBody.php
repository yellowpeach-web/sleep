<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Wcml\WcmlEmbed;

class StoreUrlsTabBody implements PageRenderInterface {


  public function render() {
    echo '<div class="wpml-tm-store-urls">';
    WcmlEmbed::embed( 'store-url' );
    echo '</div>';
  }


}
