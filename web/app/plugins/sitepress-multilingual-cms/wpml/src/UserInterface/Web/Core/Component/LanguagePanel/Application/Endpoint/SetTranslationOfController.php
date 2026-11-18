<?php

namespace WPML\UserInterface\Web\Core\Component\LanguagePanel\Application\Endpoint;

use WPML\PHP\Exception\InvalidArgumentException;
use WPML\PHP\Exception\InvalidItemIdException;

class SetTranslationOfController extends AbstractPostWriteController {

  const NONE = 'none';


  protected function handlePost( int $postId, array $requestData ): array {
    $original = $this->originalId( $requestData );
    $post     = $this->post( $postId );
    $language = $this->gateway->getElementLanguageCode( $postId );

    if ( null === $language ) {
      $this->refuseAsCallersFault( __( 'The post has no language yet; set its language first.', 'wpml' ) );
    }

    $locked = $this->refuseWhenLocked( $postId );
    if ( $locked ) {
      return $locked;
    }

    $ownTrid = $this->gateway->getElementTrid( $postId, $post['post_type'] );
    if (
      $ownTrid
      && $this->gateway->getOriginalElementId( $ownTrid ) === $postId
      && $this->gateway->countGroupMembers( $ownTrid, $post['post_type'] ) > 1
    ) {
      return $this->refuse(
        self::STATUS_CONFLICT,
        'has-translations',
        __( 'This post is the original of its translations and cannot be moved to another group while they exist.', 'wpml' )
      );
    }

    if ( null === $original ) {
      $this->gateway->setTranslationOf( $postId, $post['post_type'], null, $language, null );

      return $this->answer( $postId, $post['post_type'], null );
    }

    if ( $original === $postId ) {
      $this->refuseAsCallersFault( __( 'A post cannot be a translation of itself.', 'wpml' ) );
    }

    $originalPost = $this->gateway->getPost( $original );
    if ( null === $originalPost || $originalPost['post_type'] !== $post['post_type'] ) {
      $this->refuseAsCallersFault( __( 'There is no post of this type with that id.', 'wpml' ) );
    }

    $trid           = $this->gateway->getElementTrid( $original, $post['post_type'] );
    $sourceLanguage = $this->gateway->getElementLanguageCode( $original );
    if ( null === $trid || null === $sourceLanguage ) {
      $this->refuseAsCallersFault( __( 'That post has no language.', 'wpml' ) );
    }

    if ( $sourceLanguage === $language ) {
      $this->refuseAsCallersFault( __( 'That post is in the same language as this one.', 'wpml' ) );
    }

    $taken = $this->gateway->getElementIdInLanguage( $trid, $language, $post['post_type'] );
    if ( null !== $taken && $taken !== $postId ) {
      return $this->refuse(
        self::STATUS_CONFLICT,
        'translation-exists',
        __( 'A translation in this language already exists in the group.', 'wpml' )
      );
    }

    if ( ! $this->authorization->currentUserCanJoinGroup( $trid, 'post_' . $post['post_type'], $language ) ) {
      return $this->forbidden( __( 'You are not allowed to connect this post to that translation group.', 'wpml' ) );
    }

    $this->gateway->setTranslationOf( $postId, $post['post_type'], $trid, $language, $sourceLanguage );

    return $this->answer( $postId, $post['post_type'], $sourceLanguage );
  }


  private function originalId( array $requestData ): ?int {
    $value = $requestData['original_id'] ?? null;

    if ( is_string( $value ) && self::NONE === strtolower( trim( $value ) ) ) {
      return null;
    }

    if ( ! is_numeric( $value ) || (int) $value <= 0 ) {
      $this->refuseAsCallersFault( __( 'original_id: a positive post id or "none" is required.', 'wpml' ) );
    }

    return (int) $value;
  }


  private function answer( int $postId, string $postType, ?string $sourceLanguage ): array {
    return [
      'post_id'         => $postId,
      'trid'            => $this->gateway->getElementTrid( $postId, $postType ),
      'source_language' => $sourceLanguage,
    ];
  }


}
