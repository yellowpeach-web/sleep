<?php

namespace WPML\Core\Component\PostHog\Application\Repository;

interface SetupWizardEventQueueLockInterface {

  public function acquire(): bool;


  public function release();

}
