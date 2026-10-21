<?php

namespace WPML\Core\Component\Translation\Application\String\Repository;

use WPML\Core\Component\Translation\Application\String\StringException;

interface StringBatchRepositoryInterface {


  public function create( string $name, array $stringIds, string $sourceLanguageCode ): int;


  public function removeStrings( int $batchId, array $stringIds ): int;


  public function deleteUnsent( int $batchId );


}
