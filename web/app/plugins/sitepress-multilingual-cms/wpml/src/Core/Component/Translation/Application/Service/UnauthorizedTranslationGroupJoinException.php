<?php

namespace WPML\Core\Component\Translation\Application\Service;

use WPML\PHP\Exception\Exception;

class UnauthorizedTranslationGroupJoinException extends Exception {

  private $trid;


  public function __construct( int $trid, string $wpmlElementType, string $targetLanguage ) {
    parent::__construct(
      sprintf(
        'The current principal is not authorized to join element type "%s" into translation group %d as "%s".',
        preg_replace( '/[^a-z0-9_\-]/i', '', $wpmlElementType ),
        $trid,
        preg_replace( '/[^a-z0-9_\-]/i', '', $targetLanguage )
      )
    );
    $this->trid = $trid;
  }


  public function getTrid(): int {
    return $this->trid;
  }


}
