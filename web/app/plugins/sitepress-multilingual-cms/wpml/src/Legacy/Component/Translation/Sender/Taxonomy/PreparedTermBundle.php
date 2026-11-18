<?php

namespace WPML\Legacy\Component\Translation\Sender\Taxonomy;

final class PreparedTermBundle {

  private $record;

  private $members;

  public function __construct( PreparedTaxonomyTermJob $record, array $members ) {
    $this->record  = $record;
    $this->members = $members;
  }

  public function getRecord(): PreparedTaxonomyTermJob {
    return $this->record;
  }

  public function getMembers(): array {
    return $this->members;
  }

  public function getModel() {
    return $this->record->getModel();
  }

  public function getBatchId(): int {
    return $this->record->getTermTaxonomyId();
  }

  public function getSourceLanguage(): string {
    return $this->record->getSourceLanguage();
  }

  public function getTargetLanguage(): string {
    return $this->record->getTargetLanguage();
  }

  public function getRid(): int {
    return $this->record->getRid();
  }

  public function getJobId(): int {
    return $this->record->getJobId();
  }

  public function getTermTaxonomyIds(): array {
    return array_map(
      function ( PreparedTaxonomyTermJob $member ) {
        return $member->getTermTaxonomyId();
      },
      $this->members
    );
  }
}
