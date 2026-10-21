<?php

namespace WPML\Core\Component\Cdt\Application\Service;

class CheckinRequest {

  private $sentAt = null;


  public function markSent( int $at ): void {
    $this->sentAt = $at;
  }


  public function clear(): void {
    $this->sentAt = null;
  }


  public function getSentAt(): ?int {
    return $this->sentAt;
  }


}
