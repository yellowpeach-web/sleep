<?php

namespace WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Repository;

interface PendingTranslatableOfferRepositoryInterface {

  public function getPendingTypes(): array;

  public function commit(): void;

  public function discard(): array;
}
