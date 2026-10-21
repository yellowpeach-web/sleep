<?php

namespace WPML\UserInterface\Web\Core\Component\Notices\TeaUpgrade\Application\Dto;

class ScenarioDto {

  private $engineCodeName;

  private $descriptionPresent;

  private $teaOn;


  public function __construct( string $engineCodeName, bool $descriptionPresent, bool $teaOn ) {
    $this->engineCodeName     = $engineCodeName;
    $this->descriptionPresent = $descriptionPresent;
    $this->teaOn              = $teaOn;
  }


  public function getEngineCodeName(): string {
    return $this->engineCodeName;
  }


  public function isDescriptionPresent(): bool {
    return $this->descriptionPresent;
  }


  public function isTeaOn(): bool {
    return $this->teaOn;
  }


  public function toArray(): array {
    return [
      'engine'             => $this->engineCodeName,
      'descriptionPresent' => $this->descriptionPresent,
      'teaOn'              => $this->teaOn,
    ];
  }


}
