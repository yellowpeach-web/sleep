<?php

namespace WPML\Core\Component\WordsToTranslate\Domain\Evidence;

class Fingerprint {

  const HASH_ALGORITHM = 'sha256';


  public function forContents( $fieldContents, $termTexts = [] ) {
    $parts = array_values( $fieldContents );

    foreach ( $termTexts as $termText ) {
      $parts[] = $termText;
    }

    return hash( self::HASH_ALGORITHM, implode( "\n", $parts ) );
  }


}
