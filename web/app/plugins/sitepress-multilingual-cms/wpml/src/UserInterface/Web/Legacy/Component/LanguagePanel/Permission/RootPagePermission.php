<?php

namespace WPML\UserInterface\Web\Legacy\Component\LanguagePanel\Permission;

use WPML\UserInterface\Web\Core\SharedKernel\Config\Endpoint\PermissionInterface;

class RootPagePermission implements PermissionInterface {


  public function check( $request ): bool {
    return \WPML\UrlHandling\RootPage\RootPageCommand::restPolicy()->permits();
  }


}
