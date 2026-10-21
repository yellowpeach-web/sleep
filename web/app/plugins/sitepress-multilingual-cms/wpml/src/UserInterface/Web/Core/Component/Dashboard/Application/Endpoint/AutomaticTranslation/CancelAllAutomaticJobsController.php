<?php

namespace WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\AutomaticTranslation;

use WPML\Core\Component\Translation\Application\Service\TranslationService;
use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\PHP\Exception\RuntimeException;

class CancelAllAutomaticJobsController implements EndpointInterface {

  private $translationService;


  public function __construct( TranslationService $settingsService ) {
    $this->translationService = $settingsService;
  }


  public function handle( $requestData = null ): array {
    $result = $this->translationService->cancelAllAutomaticJobs();

    return [
      'success'   => true,
      'processed' => $result->getProcessed(),
      'hasMore'   => $result->hasMore(),
      'release'   => $result->getRelease()->toArray(),
    ];
  }


}
