<?php

namespace WPML\UserInterface\Web\Core\Component\LanguagePanel\Application\Endpoint;

use WPML\PHP\Exception\InvalidArgumentException;
use WPML\PHP\Exception\InvalidItemIdException;

class SwitchLanguageController extends AbstractPostWriteController {


  protected function handlePost( int $postId, array $requestData ): array {
    $language = $this->requiredString( $requestData, 'language' );
    if ( ! $this->gateway->isActiveLanguage( $language ) ) {
      $this->refuseAsCallersFault( __( 'language: the code of an active language is required.', 'wpml' ) );
    }

    $post = $this->post( $postId );

    $locked = $this->refuseWhenLocked( $postId );
    if ( $locked ) {
      return $locked;
    }

    if ( ! $this->gateway->switchLanguage( $postId, $post['post_type'], $language ) ) {
      return $this->refuse(
        self::STATUS_CONFLICT,
        'translation-exists',
        __( 'A translation in this language already exists in the group.', 'wpml' )
      );
    }

    return [
      'post_id'  => $postId,
      'language' => $language,
      'trid'     => $this->gateway->getElementTrid( $postId, $post['post_type'] ),
    ];
  }


}
