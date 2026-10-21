<?php

namespace WPML\Core\Component\Cdt\Application\Service;

interface SubscriptionFetchInterface {


  public function isAvailable(): bool;


  public function fetch(): void;


}
