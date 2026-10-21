<?php

namespace WPML\Infrastructure\WordPress\Component\Cdt\Service;

use WPML\Core\Component\Cdt\Application\Service\HttpPosterInterface;
use WPML\Core\SharedKernel\Component\WpmlOrgClient\Domain\WpmlOrgOrigin;

class CdtApiPoster implements HttpPosterInterface {

  const API_URL = 'https://api.wpml.org/';


  public function post( array $fields, int $timeout ): array {
    $response = wp_remote_post(
      WpmlOrgOrigin::map( self::API_URL ),
      [
        'timeout'  => $timeout,
        'blocking' => true,
        'body'     => $fields,
      ]
    );

    if ( is_wp_error( $response ) ) {
      return [ 'status' => 0, 'error' => $response->get_error_message() ];
    }

    return [ 'status' => (int) wp_remote_retrieve_response_code( $response ), 'error' => null ];
  }


}
