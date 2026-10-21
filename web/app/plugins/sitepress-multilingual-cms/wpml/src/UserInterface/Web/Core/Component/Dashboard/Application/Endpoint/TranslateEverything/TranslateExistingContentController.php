<?php

namespace WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\TranslateEverything;

use WPML\Core\Component\Translation\Application\Service\TranslateExistingContentService;
use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\PHP\Exception\InvalidArgumentException;

class TranslateExistingContentController implements EndpointInterface {

  private $service;


  public function __construct( TranslateExistingContentService $service ) {
    $this->service = $service;
  }


  public function handle( $requestData = null ): array {
    $data = is_array( $requestData ) ? $requestData : [];

    $postTypes = $this->readTypeNameList( $data['postTypes'] ?? [] );

    if ( null === $postTypes ) {
      throw new InvalidArgumentException( 'postTypes must be an array of type names.' );
    }

    $packageTypes = $this->readTypeNameList( $data['packageTypes'] ?? [] );

    if ( null === $packageTypes ) {
      throw new InvalidArgumentException( 'packageTypes must be an array of type names.' );
    }

    $this->service->handle( $postTypes, $packageTypes );

    return [
      'success' => true,
    ];
  }


  private function readTypeNameList( $value ) {
    if ( ! is_array( $value ) ) {
      return null;
    }

    $types = [];

    foreach ( $value as $type ) {
      if ( ! is_string( $type ) ) {
        return null;
      }

      $types[] = htmlspecialchars( strip_tags( $type ) );
    }

    return $types;
  }


}
