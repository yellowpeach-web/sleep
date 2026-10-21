<?php

namespace WPML\Core\Component\Translation\Application\String\Query;

use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;

interface UnsentStringBatchesQueryInterface {


  public function get( array $batchIds ): array;


}
