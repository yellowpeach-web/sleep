<?php

namespace WPML\Core\Component\PostHog\Application\Configuration;

interface PostHogOptOutRecordingConfigInterface {


  public function getDashboardSessionLimit(): int;


  public function getWindowDays(): int;


  public function isEarlyStopOnEnable(): bool;


}
