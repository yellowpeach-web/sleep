<?php

namespace WPML\UserInterface\Web\Legacy\Component\LanguagePanel\Permission;

use WPML\UserInterface\Web\Core\SharedKernel\Component\LanguagePanel\Application\PostIdList;
use WPML\UserInterface\Web\Core\SharedKernel\Config\Endpoint\PermissionInterface;

class EditPostsPermission implements PermissionInterface {


  public function check( $request ): bool {
    if ( ! is_object( $request ) || ! method_exists( $request, 'get_param' ) ) {
      return false;
    }

    foreach ( PostIdList::parse( $request->get_param( 'ids' ) ) as $id ) {
      if ( ! current_user_can( 'edit_post', $id ) ) {
        return false;
      }
    }

    return true;
  }


}
