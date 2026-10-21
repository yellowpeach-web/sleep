<?php

namespace WPML\Core\Component\CustomFieldPreferences\Application\Repository;

interface PreferenceRepositoryInterface {


  public function getMap( string $type ): array;


  public function getMaps(): array;


  public function getMode( string $type, string $name );


  public function getModes( string $type, array $names ): array;


  public function upsertMany( string $type, array $nameToMode ): bool;


  public function deleteMany( string $type, array $names ): bool;


  public function countByType( string $type ): int;


  public function resetRuntimeCache();


}
