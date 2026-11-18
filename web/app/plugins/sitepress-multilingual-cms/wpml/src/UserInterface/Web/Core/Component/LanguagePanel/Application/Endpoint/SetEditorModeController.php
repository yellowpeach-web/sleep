<?php

namespace WPML\UserInterface\Web\Core\Component\LanguagePanel\Application\Endpoint;

use WPML\PHP\Exception\InvalidArgumentException;
use WPML\PHP\Exception\InvalidItemIdException;

class SetEditorModeController extends AbstractPostWriteController {

  const SCOPE_GLOBAL    = 'global';
  const SCOPE_POST_TYPE = 'all_posts_of_type';
  const SCOPE_THIS_POST = 'this_post';

  const SCOPES  = [ self::SCOPE_GLOBAL, self::SCOPE_POST_TYPE, self::SCOPE_THIS_POST ];
  const EDITORS = [ 'dashboard', 'wpml', 'native' ];


  protected function handlePost( int $postId, array $requestData ): array {
    $editor = $this->requiredString( $requestData, 'editor' );
    $scope  = $this->requiredString( $requestData, 'scope' );

    if ( ! in_array( $editor, self::EDITORS, true ) ) {
      $this->refuseAsCallersFault(
        sprintf(
          /* translators: %s: the accepted values, comma separated. */
          __( 'editor: one of %s is required.', 'wpml' ),
          implode( ', ', self::EDITORS )
        )
      );
    }
    if ( ! in_array( $scope, self::SCOPES, true ) ) {
      $this->refuseAsCallersFault(
        sprintf(
          /* translators: %s: the accepted values, comma separated. */
          __( 'scope: one of %s is required.', 'wpml' ),
          implode( ', ', self::SCOPES )
        )
      );
    }

    $this->post( $postId );

    if ( ! $this->authorized( $scope, $postId ) ) {
      return $this->forbidden( __( 'You are not allowed to change the translation editor at this scope.', 'wpml' ) );
    }

    $this->gateway->setEditorMode( $scope, $editor, $postId, true === $this->optionalBool( $requestData, 'is_switching_wpml_native' ) );

    return [
      'post_id' => $postId,
      'editor'  => $editor,
      'scope'   => $scope,
    ];
  }


  private function authorized( string $scope, int $postId ): bool {
    if ( self::SCOPE_THIS_POST === $scope ) {
      return $this->authorization->currentUserCanManageTranslations()
        || $this->authorization->currentUserCanEditPost( $postId );
    }

    return $this->authorization->currentUserCanManageTranslations();
  }


}
