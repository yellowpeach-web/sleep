<?php

namespace WPML\Core\Component\WordsToTranslate\Domain\Evidence;

class ProofSize {

  const COMPRESSION_LEVEL = 9;


  public function rawBytes( $proof ) {
    return strlen( $this->encode( $proof ) );
  }


  public function compressedBytes( $proof ) {
    $encoded = $this->encode( $proof );
    if ( ! $this->isZlibAvailable() ) {
      return strlen( $encoded );
    }

    $compressed = gzcompress( $encoded, self::COMPRESSION_LEVEL );

    return strlen( $compressed === false ? $encoded : $compressed );
  }


  protected function isZlibAvailable() {
    return function_exists( 'gzcompress' );
  }


  private function encode( $proof ) {
    $json = json_encode( $proof, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

    return $json === false ? '' : $json;
  }


}
