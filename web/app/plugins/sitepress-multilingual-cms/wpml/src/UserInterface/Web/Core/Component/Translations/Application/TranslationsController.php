<?php

namespace WPML\UserInterface\Web\Core\Component\Translations\Application;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;

class TranslationsController implements PageRenderInterface {


  public function render() {
    $body = __(
      'Implementation pending. The Translations hub lands in a later milestone.',
      'wpml'
    );
    echo '<div class="wrap">'
      /* translators: Name of the WPML Translations screen: the admin menu item and the page heading. */
      . '<h1>' . __( 'Translations', 'wpml' ) . '</h1>'
      . '<p>' . $body . '</p>'
      . '<div id="wpml-translations-container"></div>'
      . '</div>';
  }


}
