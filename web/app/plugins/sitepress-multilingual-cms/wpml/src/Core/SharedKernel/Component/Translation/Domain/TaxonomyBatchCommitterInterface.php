<?php

namespace WPML\Core\SharedKernel\Component\Translation\Domain;

interface TaxonomyBatchCommitterInterface {

  public function commit( string $batchName, int $batchId ): bool;
}
