<?php

namespace WPML\Core\SharedKernel\Component\Translation\Domain;

interface PackageOwnerInterface {

  public function ridesOwner( int $elementId ): bool;

  public function getOwner( int $elementId ): ?array;

  public function message( ?array $owner ): string;

  public function changed( array $owner, int $packageId ): void;

}
