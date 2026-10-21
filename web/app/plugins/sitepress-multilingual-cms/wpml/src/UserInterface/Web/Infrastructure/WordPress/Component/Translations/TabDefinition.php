<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations;

class TabDefinition {

  private $id;

  private $label;

  private $tooltip;

  private $gate;

  private $badgeSlotId;


  public function __construct(
    string $id,
    string $label,
    string $tooltip,
    callable $gate,
    ?string $badgeSlotId = null
  ) {
    $this->id          = $id;
    $this->label       = $label;
    $this->tooltip     = $tooltip;
    $this->gate        = $gate;
    $this->badgeSlotId = $badgeSlotId;
  }


  public function id(): string {
    return $this->id;
  }


  public function label(): string {
    return $this->label;
  }


  public function tooltip(): string {
    return $this->tooltip;
  }


  public function isVisible(): bool {
    return ( $this->gate )();
  }


  public function badgeSlotId(): ?string {
    return $this->badgeSlotId;
  }


}
