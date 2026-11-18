<?php

namespace WPML\Core\Component\Post\Application\LanguagePanel;

interface PanelDataFilterInterface {


  public function apply( array $data, int $postId, array $context = [] ): array;


}
