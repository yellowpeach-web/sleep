<?php

namespace WPML\Core\Component\PostHog\Domain\Event\SetupWizard;

use WPML\Core\Component\PostHog\Domain\Event\Event as AbstractEvent;

class Event extends AbstractEvent implements SetupWizardLifecycleEventInterface {


  public function getName(): string {
    return 'wpml_setup_wizard_started';
  }


}
