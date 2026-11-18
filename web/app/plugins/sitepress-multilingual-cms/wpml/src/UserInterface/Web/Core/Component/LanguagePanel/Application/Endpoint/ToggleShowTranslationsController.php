<?php

namespace WPML\UserInterface\Web\Core\Component\LanguagePanel\Application\Endpoint;

use WPML\PHP\Exception\InvalidItemIdException;

class ToggleShowTranslationsController extends AbstractPostWriteController {


  protected function handlePost( int $postId, array $requestData ): array {
    $this->post( $postId );

    if ( ! $this->authorization->currentUserIsAdministrator() ) {
      return $this->forbidden( __( 'You are not allowed to change this setting.', 'wpml' ) );
    }

    return [
      'post_id'           => $postId,
      'show_translations' => $this->gateway->setShowTranslations( $this->optionalBool( $requestData, 'show' ) ),
    ];
  }


}
