<?php

namespace WPML\Core\Component\Translation\Application\Service\AutomaticJobsCancellation;

final class ReleaseSummary {

  private $releasedJobs;

  private $releasedWords;

  private $unconfirmedJobs;


  public function __construct(
    int $releasedJobs = 0,
    int $releasedWords = 0,
    int $unconfirmedJobs = 0
  ) {
    $this->releasedJobs    = max( 0, $releasedJobs );
    $this->releasedWords   = max( 0, $releasedWords );
    $this->unconfirmedJobs = max( 0, $unconfirmedJobs );
  }


  public function getReleasedJobs(): int {
    return $this->releasedJobs;
  }


  public function getReleasedWords(): int {
    return $this->releasedWords;
  }


  public function getUnconfirmedJobs(): int {
    return $this->unconfirmedJobs;
  }


  public function isConfirmed(): bool {
    return $this->releasedJobs > 0;
  }


  public function isEmpty(): bool {
    return $this->releasedJobs === 0 && $this->unconfirmedJobs === 0;
  }


  public function getJobsForCopy(): int {
    return $this->isConfirmed() ? $this->releasedJobs : $this->unconfirmedJobs;
  }


  public function toArray(): array {
    return [
      'jobs'      => $this->getJobsForCopy(),
      'words'     => $this->releasedWords,
      'confirmed' => $this->isConfirmed(),
    ];
  }

}
