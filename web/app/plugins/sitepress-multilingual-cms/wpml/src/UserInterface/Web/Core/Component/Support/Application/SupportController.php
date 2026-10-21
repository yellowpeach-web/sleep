<?php

namespace WPML\UserInterface\Web\Core\Component\Support\Application;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;

class SupportController implements PageRenderInterface {


  public function render() {
    $intro = __(
      'Implementation pending. The redesigned Support landing lands later.',
      'wpml'
    );
    $note = __(
      'The legacy Support and Troubleshooting pages remain reachable at their existing URLs.',
      'wpml'
    );
    $body = $intro . ' ' . $note;
    echo '<div class="wrap">'
      /* translators: Name of the WPML Support screen: the admin menu item, the page heading, and the back-link that returns to it. Noun (help from the WPML support team), not the verb "to support". */
      . '<h1>' . __( 'Support', 'wpml' ) . '</h1>'
      . '<p>' . $body . '</p>'
      . '<div id="wpml-support-container"></div>'
      . '</div>';
  }


}
