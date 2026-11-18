<?php

namespace WPML\UserInterface\Web\Core\Component\LanguagePanel\Application\Endpoint;

use WPML\Core\Component\Post\Application\LanguagePanel\Write\PostLanguageWriteGatewayInterface;
use WPML\Core\Component\Post\Application\LanguagePanel\Write\PostWriteAuthorizationInterface;
use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\PHP\Exception\InvalidArgumentException;
use WPML\PHP\Exception\InvalidItemIdException;

abstract class AbstractPostWriteController implements EndpointInterface {

  const STATUS_FORBIDDEN = 403;
  const STATUS_CONFLICT  = 409;
  const STATUS_LOCKED    = 423;

  protected $gateway;

  protected $authorization;


  public function __construct(
    PostLanguageWriteGatewayInterface $gateway,
    PostWriteAuthorizationInterface $authorization
  ) {
    $this->gateway       = $gateway;
    $this->authorization = $authorization;
  }


  public function handle( $requestData = null ): array {
    $requestData = is_array( $requestData ) ? $requestData : [];

    return $this->handlePost( $this->postId( $requestData ), $requestData );
  }


  abstract protected function handlePost( int $postId, array $requestData ): array;


  protected function postId( array $requestData ): int {
    $id = $requestData['id'] ?? null;
    if ( ! is_numeric( $id ) || (int) $id <= 0 ) {
      $this->refuseAsCallersFault( __( 'A positive post id is required.', 'wpml' ) );
    }

    return (int) $id;
  }


  protected function post( int $postId ): array {
    $post = $this->gateway->getPost( $postId );
    if ( null === $post ) {
      $this->refuseAsMissingPost(
        sprintf(
          /* translators: %d: the id of a post that does not exist. */
          __( 'There is no post %d.', 'wpml' ),
          $postId
        )
      );
    }

    return $post;
  }


  protected function requiredString( array $requestData, string $name ): string {
    $value = $requestData[ $name ] ?? null;
    if ( ! is_scalar( $value ) || '' === trim( (string) $value ) ) {
      $this->refuseAsCallersFault(
        sprintf(
          /* translators: %s: the name of a request parameter. */
          __( '%s: a non-empty value is required.', 'wpml' ),
          $name
        )
      );
    }

    return trim( (string) $value );
  }


  protected function refuseAsCallersFault( string $message ) {
    $fault = new InvalidArgumentException( $message );
    throw $fault;
  }


  protected function refuseAsMissingPost( string $message ) {
    $fault = new InvalidItemIdException( $message );
    throw $fault;
  }


  protected function optionalBool( array $requestData, string $name ): ?bool {
    if ( ! array_key_exists( $name, $requestData ) || null === $requestData[ $name ] ) {
      return null;
    }

    return filter_var( $requestData[ $name ], FILTER_VALIDATE_BOOLEAN );
  }


  protected function refuse( int $status, string $code, string $message ): array {
    return [
      'status'  => $status,
      'code'    => $code,
      'message' => $message,
    ];
  }


  protected function forbidden( string $message ): array {
    return $this->refuse( self::STATUS_FORBIDDEN, 'forbidden', $message );
  }


  protected function refuseWhenLocked( int $postId ): ?array {
    if ( ! $this->authorization->isPostLocked( $postId ) ) {
      return null;
    }

    return $this->refuse(
      self::STATUS_LOCKED,
      'post-locked',
      __( 'Another user is editing this post right now.', 'wpml' )
    );
  }


}
