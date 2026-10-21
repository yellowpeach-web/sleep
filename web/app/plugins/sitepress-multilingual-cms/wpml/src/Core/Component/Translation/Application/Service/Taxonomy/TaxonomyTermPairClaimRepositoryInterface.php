<?php

namespace WPML\Core\Component\Translation\Application\Service\Taxonomy;

interface TaxonomyTermPairClaimRepositoryInterface {

  public function claim( int $termTaxonomyId, string $targetLanguage, string $owner ): bool;


  public function release( int $termTaxonomyId, string $targetLanguage, string $owner ): bool;


  public function claimMany( array $pairs, string $owner ): array;


  public function releaseMany( array $pairs, string $owner ): int;

}
