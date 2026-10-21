<?php

namespace WPML\Core\Component\WordsToTranslate\Domain\Evidence;

class ProofSize {

  const COMPRESSION_LEVEL = 9;


  public function rawBytes( $proof ) {
    return strlen( $this->encode( $proof ) );
  }


  public function compressedBytes( $proof ) {
    $compressed = gzcompress( $this->encode( $proof ), self::COMPRESSION_LEVEL );

    return $compressed === false ? 0 : strlen( $compressed );
  }


  private function encode( $proof ) {
    $json = json_encode( $proof, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

    return $json === false ? '' : $json;
  }


}
