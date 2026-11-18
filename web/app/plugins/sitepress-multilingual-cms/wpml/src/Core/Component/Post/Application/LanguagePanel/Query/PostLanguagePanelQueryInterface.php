<?php

namespace WPML\Core\Component\Post\Application\LanguagePanel\Query;

use WPML\Core\Component\Post\Application\LanguagePanel\Dto\PostLanguagePanelDto;
use WPML\Core\Component\Post\Application\LanguagePanel\PanelRequestHints;
use WPML\PHP\Exception\InvalidItemIdException;

interface PostLanguagePanelQueryInterface {


  public function get( int $postId, PanelRequestHints $hints ): PostLanguagePanelDto;


  public function getMany( array $postIds, PanelRequestHints $hints ): array;


}
