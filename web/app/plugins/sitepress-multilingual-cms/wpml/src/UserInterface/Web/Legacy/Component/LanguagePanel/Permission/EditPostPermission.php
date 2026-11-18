<?php

namespace WPML\UserInterface\Web\Legacy\Component\LanguagePanel\Permission;

use WPML\UserInterface\Web\Core\SharedKernel\Config\Endpoint\PermissionInterface;

class EditPostPermission implements PermissionInterface {


  public function check( $request ): bool {
    $id = self::param( $request, 'id' );

    return null !== $id && current_user_can( 'edit_post', $id );
  }


  protected static function param( $request, string $name ): ?int {
    if ( ! is_object( $request ) || ! method_exists( $request, 'get_param' ) ) {
      return null;
    }

    $value = $request->get_param( $name );

    return is_numeric( $value ) && (int) $value > 0 ? (int) $value : null;
  }


}
