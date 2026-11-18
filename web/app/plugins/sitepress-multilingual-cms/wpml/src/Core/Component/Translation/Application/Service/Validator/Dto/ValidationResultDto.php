<?php

namespace WPML\Core\Component\Translation\Application\Service\Validator\Dto;

class ValidationResultDto {

  private $type;

  private $valid;

  private $languages;


  public function __construct( string $type, bool $valid, array $languages = [] ) {
    $this->type      = $type;
    $this->valid     = $valid;
    $this->languages = array_values( array_map( 'strval', $languages ) );
  }


  public function toArray(): array {
    $result = [
      'type'  => $this->type,
      'valid' => $this->valid,
    ];

    if ( $this->languages ) {
      $result['languages'] = $this->languages;
    }

    return $result;
  }


}
