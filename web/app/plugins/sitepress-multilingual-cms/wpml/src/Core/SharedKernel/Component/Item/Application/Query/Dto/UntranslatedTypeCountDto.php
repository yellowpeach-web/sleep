<?php

namespace WPML\Core\SharedKernel\Component\Item\Application\Query\Dto;

class UntranslatedTypeCountDto {

  private $namePlural;

  private $nameSingular;

  private $count;

  private $kind;

  private $type;

  private $heldOutCount;


  public function __construct(
    string $namePlural,
    string $nameSingular,
    int $count,
    $kind,
    string $type = '',
    int $heldOutCount = 0
  ) {
    $this->namePlural   = $namePlural;
    $this->nameSingular = $nameSingular;
    $this->count        = $count;
    $this->kind         = $kind;
    $this->type         = $type;
    $this->heldOutCount = $heldOutCount;
  }


  public function getNamePlural(): string {
    return $this->namePlural;
  }


  public function getNameSingular(): string {
    return $this->nameSingular;
  }


  public function getCount(): int {
    return $this->count;
  }


  public function getHeldOutCount(): int {
    return $this->heldOutCount;
  }


  public function toArray(): array {
    return [
      'namePlural'   => $this->namePlural,
      'nameSingular' => $this->nameSingular,
      'count'        => $this->count,
      'kind'         => $this->kind,
      'type'         => $this->type,
      'heldOutCount' => $this->heldOutCount,
    ];
  }


}
