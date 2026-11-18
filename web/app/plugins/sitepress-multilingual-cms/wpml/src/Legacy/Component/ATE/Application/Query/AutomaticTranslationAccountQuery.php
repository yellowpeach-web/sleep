<?php

namespace WPML\Legacy\Component\ATE\Application\Query;

use WPML\Core\SharedKernel\Component\ATE\Application\Query\AutomaticTranslationAccountQueryInterface;
use WPML\TranslationPolicy\Policy\Policy;

class AutomaticTranslationAccountQuery implements AutomaticTranslationAccountQueryInterface {


  public function needsAccountOnly(): bool {
    return Policy::automaticTranslationNeedsAccountOnly();
  }


  public function isUsable(): bool {
    return \WPML_TM_ATE_Status::is_account_usable();
  }


}
