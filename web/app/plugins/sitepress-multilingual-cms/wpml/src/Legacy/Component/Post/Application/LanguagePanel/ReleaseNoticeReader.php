<?php

namespace WPML\Legacy\Component\Post\Application\LanguagePanel;

use WPML\Core\Component\Post\Application\LanguagePanel\ReleaseNoticeReaderInterface;
use WPML\TM\ATE\Release\ReleaseNotices;

class ReleaseNoticeReader implements ReleaseNoticeReaderInterface {


  public function consume( int $postId ): ?array {
    $payload = ReleaseNotices::consumeUpdatePayload( $postId );

    return is_array( $payload ) ? $payload : null;
  }


}
