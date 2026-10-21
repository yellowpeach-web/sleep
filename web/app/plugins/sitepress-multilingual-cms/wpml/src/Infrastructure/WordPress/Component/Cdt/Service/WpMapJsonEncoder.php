<?php

namespace WPML\Infrastructure\WordPress\Component\Cdt\Service;

use WPML\Core\Component\Cdt\Application\Service\MapJsonEncoderInterface;

class WpMapJsonEncoder implements MapJsonEncoderInterface {


  public function encode( array $document ): string {
    $json = wp_json_encode( $document, JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT );

    return is_string( $json ) ? $json : '{}';
  }


}
