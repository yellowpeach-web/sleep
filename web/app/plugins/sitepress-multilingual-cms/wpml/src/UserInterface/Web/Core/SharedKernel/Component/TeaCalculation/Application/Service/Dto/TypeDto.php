<?php

namespace WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Service\Dto;

use JsonSerializable;

class TypeDto implements JsonSerializable {

  public $kind;

  public $type;

  public $nameSingular;

  public $namePlural;

  public $includeSince;

  public $itemsTotal;

  public $heldOutCount;

  public $displayCount;


  public function __construct(
    string $kind,
    string $type,
    string $nameSingular,
    string $namePlural,
    string $includeSince,
    int $itemsTotal,
    int $heldOutCount = 0,
    ?int $displayCount = null
  ) {
    $this->kind = $kind;
    $this->type = $type;
    $this->nameSingular = $nameSingular;
    $this->namePlural = $namePlural;
    $this->includeSince = $includeSince;
    $this->itemsTotal = $itemsTotal;
    $this->heldOutCount = $heldOutCount;
    $this->displayCount = $displayCount;
  }


  public function toArray(): array {
    return [
        'kind' => $this->kind,
        'type' => $this->type,
        'labels' => [
          'singular' => $this->nameSingular,
          'plural' => $this->namePlural,
        ],
        'includeSince' => $this->includeSince,
        'itemsTotal' => $this->itemsTotal,
        'itemsCalculated' => 0,
        'heldOutCount' => $this->heldOutCount,
        'displayCount' => $this->displayCount,
        'content' => [],
    ];
  }


  #[\ReturnTypeWillChange]
  public function jsonSerialize() {
    return $this->toArray();
  }


}
