<?php

namespace WPML\Core\Component\CustomFieldPreferences\Application\Service;

interface ExactEntryLookupInterface {


  public function hasExactEntry( string $type, string $name ): bool;


  public function hasExactEntries( string $type, array $names ): array;


}
