<?php

namespace WPML\Legacy\Component\ATE\Application\Query;

use WPML\Core\Component\ATE\Application\Query\NormalizedSuggestionsInterface;

class NormalizedSuggestions implements NormalizedSuggestionsInterface
{

  private $data = null;


  private function data(): array {
    if ( $this->data === null ) {
      $this->data = \WPML\TM\API\ATE\NormalizedSuggestions::get();
    }

    return $this->data;
  }


  public function getCount(): int {
    return $this->data()['pending'];
  }


  public function getQueued(): int {
    return $this->data()['queued'];
  }


  public function getQueuedSince(): ?string {
    $queuedSince = $this->data()['queuedSince'];

    return is_string( $queuedSince ) ? $queuedSince : null;
  }


}
