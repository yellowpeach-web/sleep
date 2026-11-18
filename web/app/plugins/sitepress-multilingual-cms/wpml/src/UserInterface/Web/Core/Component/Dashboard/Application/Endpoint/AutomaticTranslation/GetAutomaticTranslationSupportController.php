<?php

namespace WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\AutomaticTranslation;

use WPML\Core\Component\Translation\Application\Query\AutomaticTranslationSupportQueryInterface;
use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\PHP\Exception\InvalidArgumentException;

class GetAutomaticTranslationSupportController implements EndpointInterface {

  private $supportQuery;


  public function __construct( AutomaticTranslationSupportQueryInterface $supportQuery ) {
    $this->supportQuery = $supportQuery;
  }


  public function handle( $requestData = null ): array {
    $source = is_array( $requestData ) && is_string( $requestData['sourceLanguageCode'] ?? null )
      ? trim( $requestData['sourceLanguageCode'] )
      : '';

    if ( '' === $source ) {
      throw new InvalidArgumentException( esc_html__( 'Source language code cannot be empty', 'wpml' ) );
    }

    $languages = [];
    foreach ( $this->supportQuery->supportFrom( $source ) as $code => $row ) {
      $languages[ $code ] = [
        'code'                             => $code,
        'doesSupportAutomaticTranslations' => $row['supported'],
        'excludedReason'                   => $row['reason'],
      ];
    }

    return [
      'sourceLanguageCode' => $source,
      'languages'          => $languages,
    ];
  }


}
