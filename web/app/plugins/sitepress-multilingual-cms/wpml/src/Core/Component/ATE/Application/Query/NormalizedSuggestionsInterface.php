<?php

namespace WPML\Core\Component\ATE\Application\Query;

interface NormalizedSuggestionsInterface {


  public function getCount(): int;


  public function getQueued(): int;


  public function getQueuedSince(): ?string;


}
