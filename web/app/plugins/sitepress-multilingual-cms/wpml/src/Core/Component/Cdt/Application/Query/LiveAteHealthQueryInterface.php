<?php

namespace WPML\Core\Component\Cdt\Application\Query;

interface LiveAteHealthQueryInterface {


  public function getLateMode(): string;


  public function getOldestPendingHours( int $now ): ?float;


  public function getReconnectCode(): ?string;


}
