<?php

namespace WPML\Core\Component\Post\Application\LanguagePanel;

use WPML\Core\Component\Post\Application\LanguagePanel\Query\PostLanguagePanelQueryInterface;
use WPML\PHP\Exception\InvalidItemIdException;

class PostLanguageStatusService {

  private $query;

  private $filter;


  public function __construct( PostLanguagePanelQueryInterface $query, PanelDataFilterInterface $filter ) {
    $this->query  = $query;
    $this->filter = $filter;
  }


  public function get( int $postId, ?PanelRequestHints $hints = null ): array {
    $hints = $hints ?? PanelRequestHints::none();
    $dto   = $this->query->get( $postId, $hints );

    return $this->filter->apply( $dto->toArray(), $postId, self::context( $hints ) );
  }


  public function getMany( array $postIds, ?PanelRequestHints $hints = null ): array {
    $hints  = $hints ?? PanelRequestHints::none();
    $result = [];

    foreach ( $this->query->getMany( $postIds, $hints ) as $postId => $dto ) {
      $result[ $postId ] = $this->filter->apply( $dto->toArray(), $postId, self::context( $hints ) );
    }

    return $result;
  }


  private static function context( PanelRequestHints $hints ): array {
    return [ 'screen' => $hints->screen() ];
  }


}
