<?php

namespace WPML\Core\Component\Cdt\Application\Service;

interface CronSchedulerInterface {


  public function scheduleOnceNow( string $hook ): bool;


  public function scheduleOnceAt( string $hook, int $at ): bool;


}
