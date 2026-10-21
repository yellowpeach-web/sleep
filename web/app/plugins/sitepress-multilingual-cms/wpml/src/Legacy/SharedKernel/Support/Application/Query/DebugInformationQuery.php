<?php

namespace WPML\Legacy\SharedKernel\Support\Application\Query;

use WPML\Core\SharedKernel\Component\Support\Application\Query\DebugInformationQueryInterface;

class DebugInformationQuery implements DebugInformationQueryInterface {


  public function get(): array {
    if (
      ! class_exists( 'WPML_Debug_Information' ) ||
      ! isset( $GLOBALS['wpdb'], $GLOBALS['sitepress'] )
    ) {
      return [];
    }

    $debugInformation = new \WPML_Debug_Information( $GLOBALS['wpdb'], $GLOBALS['sitepress'] );
    $debug            = $debugInformation->run();

    return is_array( $debug ) ? $debug : [];
  }


}
