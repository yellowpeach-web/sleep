<?php

namespace WPML\Legacy\Component\Post\Application\Repository;

use WPML\TM\ATE\TranslateEverything\PendingOffer;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Repository\PendingTranslatableOfferRepositoryInterface;

class PendingTranslatableOfferRepository implements PendingTranslatableOfferRepositoryInterface {

  public function getPendingTypes(): array {
    return PendingOffer::types();
  }

  public function commit(): void {
    PendingOffer::commit();
  }

  public function discard(): array {
    return PendingOffer::revert();
  }
}
