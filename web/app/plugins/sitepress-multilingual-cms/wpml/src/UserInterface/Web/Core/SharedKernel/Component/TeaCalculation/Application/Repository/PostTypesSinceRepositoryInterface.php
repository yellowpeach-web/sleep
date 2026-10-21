<?php

namespace WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Repository;

use WPML\Setup\Option;

interface PostTypesSinceRepositoryInterface {


  public function getPostTypesSinceDates(): array;


  public function updatePostTypeSinceDate( string $type, string $since ): void;


  public function applySinceDates( array $sinceDatesByType ): array;


  public function holdBack( array $types ): void;

  public function discard( array $types ): void;


  public function fillMissingSinceDates( array $types, string $default = Option::SINCE_DATE_ALL ): void;


  public function markPostTypeAsUncompleted( string $type ): void;


  public function markPostTypeAsCompleted( string $type ): void;


  public function markPackageKindAsUncompleted( string $kind ): void;


  public function markPackageKindAsCompleted( string $kind ): void;


}
