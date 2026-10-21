<?php

namespace WPML\Legacy\Component\Translation\Sender\Taxonomy;

final class PreparedTaxonomyTermJob {

  private $model;

  private $termTaxonomyId;

  private $taxonomy;

  private $sourceLanguage;

  private $targetLanguage;

  private $translationId;

  private $rid;

  private $jobId;

  private $insertedJobId;

  private $statusBefore;


  public function __construct(
    $model,
    int $termTaxonomyId,
    string $taxonomy,
    string $sourceLanguage,
    string $targetLanguage,
    int $translationId,
    int $rid,
    int $jobId,
    int $insertedJobId,
    array $statusBefore
  ) {
    $this->model            = $model;
    $this->termTaxonomyId   = $termTaxonomyId;
    $this->taxonomy         = $taxonomy;
    $this->sourceLanguage   = $sourceLanguage;
    $this->targetLanguage   = $targetLanguage;
    $this->translationId    = $translationId;
    $this->rid              = $rid;
    $this->jobId            = $jobId;
    $this->insertedJobId    = $insertedJobId;
    $this->statusBefore     = $statusBefore;
  }


  public function getModel() {
    return $this->model;
  }


  public function getTermTaxonomyId(): int {
    return $this->termTaxonomyId;
  }


  public function getTaxonomy(): string {
    return $this->taxonomy;
  }


  public function getSourceLanguage(): string {
    return $this->sourceLanguage;
  }


  public function getTargetLanguage(): string {
    return $this->targetLanguage;
  }


  public function getTranslationId(): int {
    return $this->translationId;
  }


  public function getRid(): int {
    return $this->rid;
  }


  public function getJobId(): int {
    return $this->jobId;
  }


  public function getInsertedJobId(): int {
    return $this->insertedJobId;
  }


  public function getStatusBefore(): array {
    return $this->statusBefore;
  }

}
