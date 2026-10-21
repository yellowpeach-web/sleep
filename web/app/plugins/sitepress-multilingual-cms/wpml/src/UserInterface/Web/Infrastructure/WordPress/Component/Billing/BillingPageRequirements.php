<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Billing;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRequirementsInterface;

class BillingPageRequirements implements PageRequirementsInterface {


  public function requirementsMet() {
    return \WPML_TM_ATE_Status::is_enabled();
  }


}
