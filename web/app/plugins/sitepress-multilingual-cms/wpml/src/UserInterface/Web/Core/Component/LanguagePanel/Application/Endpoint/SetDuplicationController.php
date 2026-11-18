<?php

namespace WPML\UserInterface\Web\Core\Component\LanguagePanel\Application\Endpoint;

use WPML\Core\Component\Post\Application\LanguagePanel\PanelRequestHints;
use WPML\Core\Component\Post\Application\LanguagePanel\PostLanguageStatusService;
use WPML\Core\Component\Post\Application\LanguagePanel\Write\PostLanguageWriteGatewayInterface;
use WPML\Core\Component\Post\Application\LanguagePanel\Write\PostWriteAuthorizationInterface;
use WPML\PHP\Exception\InvalidArgumentException;
use WPML\PHP\Exception\InvalidItemIdException;

class SetDuplicationController extends AbstractPostWriteController {

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
    $post     = $this->post( $postId );
    $language = $this->gateway->getElementLanguageCode( $postId );
    $trid     = $this->gateway->getElementTrid( $postId, $post['post_type'] );
    $original = $trid ? $this->gateway->getOriginalElementId( $trid ) : null;

    if ( null === $language || null === $original || $original === $postId ) {
      $this->refuseAsCallersFault( __( 'Only a translation can be overwritten with its original.', 'wpml' ) );
    }

    if ( ! $this->authorization->currentUserIsTranslatorOrHigher() ) {
      return $this->forbidden( __( 'You are not allowed to duplicate posts.', 'wpml' ) );
    }

    if ( ! $this->authorization->currentUserCanEditPost( $original ) || ! $this->authorization->currentUserCanEditPost( $postId ) ) {
      return $this->forbidden( __( 'You are not allowed to edit this post.', 'wpml' ) );
    }

    $locked = $this->refuseWhenLocked( $postId );
    if ( $locked ) {
      return $locked;
    }

    if ( false === $this->gateway->setDuplication( $original, $language ) ) {
      return $this->refuse(
        self::STATUS_CONFLICT,
        'duplication-paused',
        __( 'Translation into this language is paused.', 'wpml' )
      );
    }

    return $this->panel->get( $postId, PanelRequestHints::fromArray( [] ) );
  }


}
