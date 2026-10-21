<?php

namespace WPML\Core\Component\WordsToTranslate\Domain\Post;

class JobDto {

  private $content;

  private $fields;

  private $fieldContents;


  public function __construct( $content, $fields, $fieldContents = null ) {
    $this->content = $content;
    $this->fields = $fields;
    $this->fieldContents = $fieldContents;
  }


  public function getContent(): string {
    return $this->content;
  }


  public function getTranslatableFields() {
    return $this->fields;
  }


  public function getFieldContents() {
    return $this->fieldContents;
  }


}
