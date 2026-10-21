<?php

namespace WPML\Core\SharedKernel\Component\Taxonomy\Application\Query\Dto;

class TaxonomyDto {

  private $id;

  private $singular;

  private $plural;


  public function __construct( string $id, string $singular, string $plural ) {
    $this->id       = $id;
    $this->singular = $singular;
    $this->plural   = $plural;
  }


  public function getId(): string {
    return $this->id;
  }


  public function getSingular(): string {
    return $this->singular;
  }


  public function getPlural(): string {
    return $this->plural;
  }


}
