<?php

namespace WPML\Core\Component\Post\Application\Query\Criteria;

use WPML\PHP\ConstructableFromArrayInterface;
use WPML\PHP\ConstructableFromArrayTrait;

final class TaxonomyTermCriteria implements ConstructableFromArrayInterface
{

    use ConstructableFromArrayTrait;

    private $taxonomyId;

    private $search;

    private $limit;

    private $offset;

    private $sourceLanguageCode;

    private $hideEmpty;

    private $orderBy;

    private $parentTtid;

    private $includeTtids;

    private $originalsOnly;


  public function __construct(
        string $taxonomyId,
        string $sourceLanguageCode,
        ?string $search = null,
        ?int $limit = null,
        ?int $offset = null,
        bool $hideEmpty = true,
        string $orderBy = 'name',
        ?int $parentTtid = null,
        array $includeTtids = [],
        bool $originalsOnly = false
    ) {
      $this->taxonomyId         = $taxonomyId;
      $this->search             = $search;
      $this->limit              = $limit;
      $this->offset             = $offset;
      $this->sourceLanguageCode = $sourceLanguageCode;
      $this->hideEmpty          = $hideEmpty;
      $this->orderBy            = $orderBy;
      $this->parentTtid         = $parentTtid;
      $this->includeTtids       = array_map( 'intval', $includeTtids );
      $this->originalsOnly      = $originalsOnly;
  }


  public function getTaxonomyId(): string {
      return $this->taxonomyId;
  }


  public function getSourceLanguageCode(): string {
      return $this->sourceLanguageCode;
  }


  public function getSearch() {
      return $this->search;
  }


  public function getLimit() {
      return $this->limit;
  }


  public function getOffset() {
      return $this->offset;
  }


  public function getHideEmpty(): bool {
      return $this->hideEmpty;
  }


  public function getOrderBy(): string {
      return $this->orderBy;
  }


  public function getParentTtid(): ?int {
      return $this->parentTtid;
  }


  public function getIncludeTtids(): array {
      return $this->includeTtids;
  }


  public function getOriginalsOnly(): bool {
      return $this->originalsOnly;
  }


}
