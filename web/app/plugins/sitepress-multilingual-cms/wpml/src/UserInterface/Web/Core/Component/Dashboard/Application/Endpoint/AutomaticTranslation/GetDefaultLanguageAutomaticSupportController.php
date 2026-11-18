<?php

namespace WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\AutomaticTranslation;

use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\Core\SharedKernel\Component\Language\Application\Query\LanguagesQueryInterface;

class GetDefaultLanguageAutomaticSupportController implements EndpointInterface {

  private $languagesQuery;


  public function __construct( LanguagesQueryInterface $languagesQuery ) {
    $this->languagesQuery = $languagesQuery;
  }


  public function handle( $requestData = null ): array {
    $default = $this->languagesQuery->getDefault();

    return [
      'code'                             => $default->getCode(),
      'doesSupportAutomaticTranslations' => $default->doesSupportAutomaticTranslations(),
    ];
  }


}
