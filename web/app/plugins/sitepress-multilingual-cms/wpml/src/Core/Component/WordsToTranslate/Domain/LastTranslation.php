<?php

namespace WPML\Core\Component\WordsToTranslate\Domain;

class LastTranslation {

  private $langCode;

  private $originalContent;

  private $originalFieldContents;

  private $wordsToTranslate;

  private $fieldCounts;

  private $fieldDiffs;

  private $diffWordsToOriginal;


  public function __construct(
    string $langCode
  ) {
    $this->langCode = $langCode;
  }


  public function getLangCode() {
    return $this->langCode;
  }


  public function setOriginalContent( string $content ) {
    $this->originalContent = $content;
  }


  public function getOriginalContent() {
    return $this->originalContent;
  }


  public function setOriginalFieldContents( $fieldContents ) {
    $this->originalFieldContents = $fieldContents;
  }


  public function getOriginalFieldContents() {
    return $this->originalFieldContents;
  }


  public function setFieldCounts( $fieldCounts ) {
    $this->fieldCounts = $fieldCounts;
  }


  public function getFieldCounts() {
    return $this->fieldCounts;
  }


  public function setFieldDiffs( $fieldDiffs ) {
    $this->fieldDiffs = $fieldDiffs;
  }


  public function getFieldDiffs() {
    return $this->fieldDiffs;
  }


  public function setWordsToTranslate( $wordsToTranslate ) {
    $this->wordsToTranslate = $wordsToTranslate;
  }


  public function getWordsToTranslate() {
    return $this->wordsToTranslate;
  }


  public function setDiffWordsToOriginal( $diff ) {
    $this->diffWordsToOriginal = $diff;
  }


  public function getDiffWordsToOriginal() {
    return $this->diffWordsToOriginal;
  }


}
