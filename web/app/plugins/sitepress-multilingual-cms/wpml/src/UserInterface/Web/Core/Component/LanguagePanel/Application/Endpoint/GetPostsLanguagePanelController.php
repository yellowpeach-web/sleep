<?php

namespace WPML\UserInterface\Web\Core\Component\LanguagePanel\Application\Endpoint;

use WPML\Core\Component\Post\Application\LanguagePanel\PanelRequestHints;
use WPML\Core\Component\Post\Application\LanguagePanel\PostLanguageStatusService;
use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\PHP\Exception\InvalidArgumentException;
use WPML\UserInterface\Web\Core\SharedKernel\Component\LanguagePanel\Application\PostIdList;

class GetPostsLanguagePanelController implements EndpointInterface {

  private $service;


  public function __construct( PostLanguageStatusService $service ) {
    $this->service = $service;
  }


  public function handle( $requestData = null ): array {
    $requestData = is_array( $requestData ) ? $requestData : [];
    $raw         = $requestData['ids'] ?? null;

    if ( PostIdList::exceedsTheCap( $raw ) ) {
      throw new InvalidArgumentException( esc_html( 'ids: at most ' . PostIdList::MAX_IDS . ' post ids per request.' ) );
    }

    $ids = PostIdList::parse( $raw );
    if ( ! $ids ) {
      throw new InvalidArgumentException( 'ids: at least one positive post id is required.' );
    }

    return [ 'posts' => $this->service->getMany( $ids, PanelRequestHints::none() ) ];
  }


}
