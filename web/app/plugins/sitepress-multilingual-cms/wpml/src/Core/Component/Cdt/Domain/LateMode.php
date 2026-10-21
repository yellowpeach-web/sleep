<?php

namespace WPML\Core\Component\Cdt\Domain;

class LateMode {

  const PUSH_OK     = 'push_ok';
  const PULL_ACTIVE = 'pull_active';
  const PULL_IDLE   = 'pull_idle';
  const SUSPECT     = 'suspect';

  const ALL = [ self::PUSH_OK, self::PULL_ACTIVE, self::PULL_IDLE, self::SUSPECT ];


  public static function normalize( $mode ): string {
    foreach ( self::ALL as $known ) {
      if ( $mode === $known ) {
        return $known;
      }
    }

    return self::PUSH_OK;
  }


}
