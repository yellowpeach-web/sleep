<?php

namespace WPML\Core\Component\Post\Application\LanguagePanel;

interface ReleaseNoticeReaderInterface {


  public function consume( int $postId ): ?array;


}
