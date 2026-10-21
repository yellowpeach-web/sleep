<?php

namespace WPML\Legacy\Component\Post\Application\Repository;

use WPML\TM\ATE\TranslateEverything\ParkedTypesOffer;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Repository\ParkedTypesOfferRepositoryInterface;

class ParkedTypesOfferRepository implements ParkedTypesOfferRepositoryInterface {

  public function getParkedTypes(): array {
    return ParkedTypesOffer::offered();
  }


  public function getTranslatableTypes(): array {
    return ParkedTypesOffer::translatableTypes();
  }


  public function record( array $types, string $door ): void {
    ParkedTypesOffer::recordTypes( $types, $door );
  }


  public function forget( array $types ): void {
    ParkedTypesOffer::forget( $types );
  }


  public function dismiss( array $types ): void {
    ParkedTypesOffer::dismiss( $types );
  }


  public function rememberLabels( array $labelsByType ): void {
    ParkedTypesOffer::rememberLabels( $labelsByType );
  }


  public function getTypeLabels(): array {
    return ParkedTypesOffer::labels();
  }
}
