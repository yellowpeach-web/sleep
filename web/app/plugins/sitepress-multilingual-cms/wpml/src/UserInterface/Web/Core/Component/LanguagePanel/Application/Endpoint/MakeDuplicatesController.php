<?php

namespace WPML\UserInterface\Web\Core\Component\LanguagePanel\Application\Endpoint;

use WPML\Core\Component\Post\Application\LanguagePanel\PanelRequestHints;
use WPML\Core\Component\Post\Application\LanguagePanel\PostLanguageStatusService;
use WPML\Core\Component\Post\Application\LanguagePanel\Write\PostLanguageWriteGatewayInterface;
use WPML\Core\Component\Post\Application\LanguagePanel\Write\PostWriteAuthorizationInterface;
use WPML\PHP\Exception\InvalidArgumentException;
use WPML\PHP\Exception\InvalidItemIdException;
use WPML\TranslationPolicy\Duplicate\DuplicateControls;

class MakeDuplicatesController extends AbstractPostWriteController {

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
    $languages = $this->languages( $requestData );
    $post      = $this->post( $postId );

    if ( ! $this->authorization->currentUserCanEditPost( $postId ) ) {
      return $this->forbidden( __( 'You are not allowed to edit this post.', 'wpml' ) );
    }

    if ( ! DuplicateControls::offered() ) {
      return $this->refuse( self::STATUS_CONFLICT, 'duplicate-retired', DuplicateControls::refusal() );
    }

    $locked = $this->refuseWhenLocked( $postId );
    if ( $locked ) {
      return $locked;
    }

    $sourceLanguage = (string) $this->gateway->getElementLanguageCode( $postId );
    $trid           = $this->gateway->getElementTrid( $postId, $post['post_type'] );

    foreach ( $languages as $language ) {
      if ( ! $this->authorization->isLangPairAllowed( $sourceLanguage, $language, $postId ) ) {
        return $this->forbidden( __( "You don't have the rights to duplicate to this language.", 'wpml' ) );
      }

      $existingTarget = $trid ? $this->gateway->getElementIdInLanguage( $trid, $language, $post['post_type'] ) : null;
      if ( $existingTarget && ! $this->authorization->currentUserCanEditPost( $existingTarget ) ) {
        return $this->forbidden( __( 'You are not allowed to overwrite the existing translation.', 'wpml' ) );
      }

      $targetLocked = $existingTarget ? $this->refuseWhenLocked( $existingTarget ) : null;
      if ( $targetLocked ) {
        return $targetLocked;
      }
    }

    $this->gateway->makeDuplicates( $postId, $languages );

    return $this->panel->get( $postId, PanelRequestHints::fromArray( [] ) );
  }


  private function languages( array $requestData ): array {
    $raw = $requestData['languages'] ?? null;
    if ( is_string( $raw ) ) {
      $raw = explode( ',', $raw );
    }
    if ( ! is_array( $raw ) ) {
      $this->refuseAsCallersFault( __( 'languages: a list of language codes is required.', 'wpml' ) );
    }

    $languages = [];
    foreach ( $raw as $code ) {
      if ( ! is_scalar( $code ) || '' === trim( (string) $code ) ) {
        $this->refuseAsCallersFault( __( 'languages: every item must be a language code.', 'wpml' ) );
      }
      $languages[] = trim( (string) $code );
    }

    $languages = array_values( array_unique( $languages ) );
    if ( ! $languages ) {
      $this->refuseAsCallersFault( __( 'languages: at least one language code is required.', 'wpml' ) );
    }

    foreach ( $languages as $language ) {
      if ( ! $this->gateway->isActiveLanguage( $language ) ) {
        $this->refuseAsCallersFault( __( 'languages: every item must be the code of an active language.', 'wpml' ) );
      }
    }

    return $languages;
  }


}
