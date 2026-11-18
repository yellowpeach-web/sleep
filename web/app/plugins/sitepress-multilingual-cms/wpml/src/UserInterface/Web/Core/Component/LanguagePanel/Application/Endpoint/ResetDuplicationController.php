<?php

namespace WPML\UserInterface\Web\Core\Component\LanguagePanel\Application\Endpoint;

use WPML\Core\Component\Post\Application\LanguagePanel\PanelRequestHints;
use WPML\Core\Component\Post\Application\LanguagePanel\PostLanguageStatusService;
use WPML\Core\Component\Post\Application\LanguagePanel\Write\PostLanguageWriteGatewayInterface;
use WPML\Core\Component\Post\Application\LanguagePanel\Write\PostWriteAuthorizationInterface;
use WPML\PHP\Exception\InvalidItemIdException;

class ResetDuplicationController extends AbstractPostWriteController {

  private $panel;


  public function __construct(
    PostLanguageWriteGatewayInterface $gateway,
    PostWriteAuthorizationInterface $authorization,
    PostLanguageStatusService $panel
  ) {
    parent::__construct( $gateway, $authorization );
    $this->panel = $panel;
  }


  protected function handlePost( int $postId, array $requestData ): array {
    $this->post( $postId );

    if ( ! $this->authorization->currentUserIsTranslatorOrHigher() ) {
      return $this->forbidden( __( 'You are not allowed to duplicate posts.', 'wpml' ) );
    }

    if ( ! $this->authorization->currentUserCanEditPost( $postId ) ) {
      return $this->forbidden( __( 'You are not allowed to edit this post.', 'wpml' ) );
    }

    $locked = $this->refuseWhenLocked( $postId );
    if ( $locked ) {
      return $locked;
    }

    $this->gateway->resetDuplication( $postId );

    return $this->panel->get( $postId, PanelRequestHints::fromArray( [] ) );
  }


}
