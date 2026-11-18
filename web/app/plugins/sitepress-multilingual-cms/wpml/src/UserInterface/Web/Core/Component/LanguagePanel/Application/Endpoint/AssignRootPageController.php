<?php

namespace WPML\UserInterface\Web\Core\Component\LanguagePanel\Application\Endpoint;

use WPML\PHP\Exception\InvalidArgumentException;
use WPML\PHP\Exception\InvalidItemIdException;

class AssignRootPageController extends AbstractPostWriteController {


  protected function handlePost( int $postId, array $requestData ): array {
    $post = $this->post( $postId );
    if ( 'page' !== $post['post_type'] ) {
      $this->refuseAsCallersFault( __( 'Only a page can be the root page.', 'wpml' ) );
    }

    if ( ! $this->authorization->currentUserCanAssignRootPage() ) {
      return $this->forbidden( __( 'You are not allowed to change the root page.', 'wpml' ) );
    }

    if ( ! $this->authorization->currentUserCanEditPost( $postId ) ) {
      return $this->forbidden( __( 'You are not allowed to edit this page.', 'wpml' ) );
    }

    if ( $this->gateway->getRootPageId() === $postId ) {
      return [
        'post_id'  => $postId,
        'assigned' => false,
      ];
    }

    $locked = $this->refuseWhenLocked( $postId );
    if ( $locked ) {
      return $locked;
    }

    $this->gateway->assignRootPage( $postId );

    return [
      'post_id'  => $postId,
      'assigned' => true,
    ];
  }


}
