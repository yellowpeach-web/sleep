<?php

namespace WPML\Core\Component\PostHog\Domain\Event\SetupWizard\WizardFirstStepCompleted;

use WPML\Core\Component\PostHog\Domain\Event\Event as AbstractEvent;
use WPML\Core\Component\PostHog\Domain\Event\SetupWizard\SetupWizardLifecycleEventInterface;

class Event extends AbstractEvent implements SetupWizardLifecycleEventInterface {


  public function getName(): string {
    return 'wpml_setup_wizard_first_step_completed';
  }


}
