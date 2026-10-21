<?php

namespace WPML\Core\Component\CustomFieldPreferences\Application\Repository;

interface BlobMapsRepositoryInterface {


  public function getMaps(): array;


  public function resaveForCutover();


  public function restore( array $mapsByType ): bool;


  public function restoreMaps( array $mapsByType ): bool;


}
