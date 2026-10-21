<?php

namespace WPML\Core\Component\WordsToTranslate\Domain\Job;

use WPML\Core\Component\WordsToTranslate\Domain\Evidence\Manifest;

class JobDTO {

  private $id;

  private $wordsToTranslate;

  private $automaticTranslationCosts;

  private $previousAteJobIds;

  private $evidenceManifest;


  public function __construct(
    $id,
    $wordsToTranslate,
    $automaticTranslationCosts,
    $previousAteJobId = [],
    $evidenceManifest = null
  ) {
    $this->id = $id;
    $this->wordsToTranslate = $wordsToTranslate;
    $this->automaticTranslationCosts = $automaticTranslationCosts;
    $this->previousAteJobIds = $previousAteJobId;
    $this->evidenceManifest = $evidenceManifest;
  }


  public function getId(): int {
    return $this->id;
  }


  public function getWordsToTranslate(): int {
    return $this->wordsToTranslate;
  }


  public function getAutomaticTranslationCosts() {
    return $this->automaticTranslationCosts;
  }


  public function getPreviousAteJobIds() {
    return $this->previousAteJobIds;
  }


  public function getEvidenceManifest() {
    return $this->evidenceManifest;
  }


}
