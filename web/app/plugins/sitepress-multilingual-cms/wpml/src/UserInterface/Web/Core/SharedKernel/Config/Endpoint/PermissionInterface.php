<?php

namespace WPML\UserInterface\Web\Core\SharedKernel\Config\Endpoint;

interface PermissionInterface {


  public function check( $request ): bool;


}
