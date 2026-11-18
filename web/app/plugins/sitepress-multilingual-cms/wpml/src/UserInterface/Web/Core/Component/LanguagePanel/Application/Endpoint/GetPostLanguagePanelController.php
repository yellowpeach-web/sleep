<?php

namespace WPML\UserInterface\Web\Core\Component\LanguagePanel\Application\Endpoint;

use WPML\Core\Component\Post\Application\LanguagePanel\PanelRequestHints;
use WPML\Core\Component\Post\Application\LanguagePanel\PostLanguageStatusService;
use WPML\Core\Component\Post\Application\LanguagePanel\ReleaseNoticeReaderInterface;
use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\PHP\Exception\InvalidArgumentException;
use WPML\PHP\Exception\InvalidItemIdException;

class GetPostLanguagePanelController implements EndpointInterface {

  private $service;

  private $releaseNotice;


  public function __construct( PostLanguageStatusService $service, ReleaseNoticeReaderInterface $releaseNotice ) {
    $this->service       = $service;
    $this->releaseNotice = $releaseNotice;
  }


  public function handle( $requestData = null ): array {
    $requestData = is_array( $requestData ) ? $requestData : [];

    $id = $requestData['id'] ?? null;
    if ( ! is_numeric( $id ) || (int) $id <= 0 ) {
      throw new InvalidArgumentException( 'id: a positive post id is required.' );
    }

    $data = $this->service->get( (int) $id, PanelRequestHints::fromArray( $requestData ) );

    $notice = $this->releaseNotice->consume( (int) $id );
    if ( null !== $notice ) {
      $data['release_notice'] = $notice;
    }

    return $data;
  }


}
