<?php

namespace WPML\Core\SharedKernel\Component\WpmlOrgClient\Domain\Api;

use WPML\Core\SharedKernel\Component\WpmlOrgClient\Domain\WpmlOrgOrigin;

class ApiUrl {

  const API_URL = 'https://api.wpml.org';


  public function get(): string {
    return defined( 'OTGS_INSTALLER_WPML_API_URL' ) ?
      constant( 'OTGS_INSTALLER_WPML_API_URL' ) :
      WpmlOrgOrigin::api();
  }


}
