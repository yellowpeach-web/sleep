<?php

namespace WPML\Core\Component\Translation\Application\Query;

interface NewestJobsForPairsQueryInterface {


  public function getForElements( string $elementTypePrefix, array $elementIds, array $languageCodes ): array;


  public function getForStringsOfBatch( int $batchId, string $languageCode ): array;


  public function getStringIdsOfBatch( int $batchId ): array;


}
