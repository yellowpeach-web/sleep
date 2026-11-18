<?php

namespace WPML\Legacy\Component\WordsToTranslate\Domain;

use WPML\Core\Component\WordsToTranslate\Domain\BareUrl as CoreBareUrl;

class BareUrl {

  public static function matches( string $value ): bool {
    return CoreBareUrl::matches( $value );
  }

}
