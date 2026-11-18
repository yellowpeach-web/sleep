<?php

namespace WPML\Core\Component\WordsToTranslate\Domain\Post\Term;

class Term {

  private $id;

  private $contents = [];

  private $taxonomy;


  public function __construct(
    int $id
  ) {
    $this->id = $id;
  }


  public function getId() {
    return $this->id;
  }


  public function setTaxonomy( string $taxonomy ) {
    $this->taxonomy = $taxonomy;
  }


  public function getTaxonomy() {
    return $this->taxonomy;
  }


  public function addContent( TermContent $content ) {
    $this->contents[] = $content;
  }


  public function getContents() {
    return $this->contents;
  }


}
