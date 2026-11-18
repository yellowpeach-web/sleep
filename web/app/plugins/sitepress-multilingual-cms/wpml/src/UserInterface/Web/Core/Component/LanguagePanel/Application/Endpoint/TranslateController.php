<?php

namespace WPML\UserInterface\Web\Core\Component\LanguagePanel\Application\Endpoint;

use WPML\Core\Component\Post\Application\LanguagePanel\SiteEditorEntityTypes;
use WPML\PHP\Exception\InvalidArgumentException;
use WPML\PHP\Exception\InvalidItemIdException;

class TranslateController extends AbstractPostWriteController {

  const VERBS = [ 'translate', 'edit', 'review' ];


  protected function handlePost( int $postId, array $requestData ): array {
    $language = $this->requiredString( $requestData, 'language' );

    $verb = isset( $requestData['verb'] ) && is_scalar( $requestData['verb'] ) && '' !== (string) $requestData['verb']
      ? (string) $requestData['verb']
      : 'translate';
    if ( ! in_array( $verb, self::VERBS, true ) ) {
      $this->refuseAsCallersFault(
        sprintf(
          /* translators: %s: the accepted values, comma separated. */
          __( 'verb: one of %s is required.', 'wpml' ),
          implode( ', ', self::VERBS )
        )
      );
    }

    $currentUrl = isset( $requestData['currentUrl'] ) && is_scalar( $requestData['currentUrl'] )
      ? (string) $requestData['currentUrl']
      : null;

    $post = $this->post( $postId );

    if ( $this->authorization->currentUserHoldsAdministratorRole() ) {
      $this->gateway->enableAmsSubscription( $this->authorization->currentUserId() );
    }

    $intent = [
      'trid'           => (int) $this->gateway->getElementTrid( $postId, $post['post_type'] ),
      'targetLanguage' => $language,
      'verb'           => $verb,
      'currentUrl'     => $currentUrl,
    ];

    if ( isset( $requestData['screen'] ) && SiteEditorEntityTypes::SCREEN === $requestData['screen'] ) {
      $intent['screen'] = SiteEditorEntityTypes::SCREEN;
    }

    if (
      isset( $requestData['renderedAt'] )
      && is_scalar( $requestData['renderedAt'] )
      && is_numeric( $requestData['renderedAt'] )
      && (int) $requestData['renderedAt'] > 0
    ) {
      $intent['renderedAt'] = (int) $requestData['renderedAt'];
    }

    $listedLanguage = $this->gateway->getElementLanguageCode( $postId );
    if ( null !== $listedLanguage ) {
      $intent['sourceLanguage'] = $listedLanguage;
    }

    return $this->gateway->resolveTranslationIntent( $intent );
  }


}
