<?php

namespace WPML\Core\Component\Translation\Domain;

final class PendingTranslationGroup {

  private $trid;

  private $sourceLanguageCode;


  public function __construct( int $trid, string $sourceLanguageCode ) {
    $this->trid               = $trid;
    $this->sourceLanguageCode = $sourceLanguageCode;
  }


  public function getTrid(): int {
    return $this->trid;
  }


  public function getSourceLanguageCode(): string {
    return $this->sourceLanguageCode;
  }


}
