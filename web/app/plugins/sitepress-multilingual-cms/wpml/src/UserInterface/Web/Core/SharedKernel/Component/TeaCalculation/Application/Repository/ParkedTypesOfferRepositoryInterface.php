<?php

namespace WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Repository;

interface ParkedTypesOfferRepositoryInterface {

  const DOOR_DASHBOARD = 'dashboard';

  public function getParkedTypes(): array;

  public function getTranslatableTypes(): array;

  public function record( array $types, string $door ): void;

  public function forget( array $types ): void;

  public function dismiss( array $types ): void;

  public function rememberLabels( array $labelsByType ): void;

  public function getTypeLabels(): array;
}
