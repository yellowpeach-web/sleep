<?php

namespace WPML\Core\Port\Appearance;

interface AppearanceReaderInterface {

  public function has( string $type, int $id ): ?bool;


  public function filterIds( string $type, array $ids ): ?array;


  public function countMissing( string $type ): ?int;


  public function heldTypes(): array;


  public function existsSql( string $typeExpr, string $idExpr ): string;


}
