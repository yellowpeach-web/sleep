<?php

namespace WPML\Core\Component\LanguageEditor\Domain\Repository;

interface LabelsRepositoryInterface {


  public function get( string $code, array $targetCodes ): array;


  public function save( string $code, array $labels ): int;


}
