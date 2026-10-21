<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Wcml;

class WcmlBonded {


  public static function rendersEmbeddedBodies(): bool {
    return has_filter( 'wpml_render_embedded_wcml_body' ) !== false;
  }


}
