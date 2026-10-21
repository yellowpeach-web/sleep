<?php

namespace WPML\Infrastructure\WordPress\Component\Cdt\Service;

use WPML\Core\Component\Cdt\Application\Service\JsonEncoderInterface;

class WpJsonEncoder implements JsonEncoderInterface {


  public function encode( array $document ): string {
    $json = wp_json_encode( $document, JSON_UNESCAPED_SLASHES );

    return is_string( $json ) ? $json : '{}';
  }


}
