<?php

namespace WPML\Core\SharedKernel\Component\WpmlOrgClient\Domain\Api;

use WPML\Core\SharedKernel\Component\WpmlOrgClient\Domain\WpmlOrgOrigin;

class SupportUrl {

  public function get(): string {
    return defined( 'WPML_SUPPORT_ORIGIN' ) ?
      rtrim( constant( 'WPML_SUPPORT_ORIGIN' ), '/' ) :
      WpmlOrgOrigin::app();
  }


}
