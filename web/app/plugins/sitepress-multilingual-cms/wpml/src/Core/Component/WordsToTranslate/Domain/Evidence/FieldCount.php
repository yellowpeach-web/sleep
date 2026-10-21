<?php

namespace WPML\Core\Component\WordsToTranslate\Domain\Evidence;

class FieldCount {

  private $key;

  private $total;

  private $charged;

  private $rawTokenCount;

  private $chargedTokensBefore;


  public function __construct(
    string $key,
    int $total,
    int $charged,
    int $rawTokenCount = 0,
    int $chargedTokensBefore = 0
  ) {
    $this->key = $key;
    $this->total = $total;
    $this->charged = $charged;
    $this->rawTokenCount = $rawTokenCount;
    $this->chargedTokensBefore = $chargedTokensBefore;
  }


  public function getKey(): string {
    return $this->key;
  }


  public function getTotal(): int {
    return $this->total;
  }


  public function getCharged(): int {
    return $this->charged;
  }


  public function getRawTokenCount(): int {
    return $this->rawTokenCount;
  }


  public function getChargedTokensBefore(): int {
    return $this->chargedTokensBefore;
  }


}
