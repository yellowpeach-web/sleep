<?php

namespace WPML\Core\Component\Translation\Application\Service\AutomaticJobsCancellation;

final class BatchResult {

  private $processed = 0;

  private $hasMore = false;

  private $failureCodes = [];

  private $releasedJobs = 0;

  private $releasedWords = 0;

  private $unconfirmedReleaseJobs = 0;


  public function addProcessed( int $processed ): void {
    $this->processed += max( 0, $processed );
  }


  public function getProcessed(): int {
    return $this->processed;
  }


  public function markHasMore(): void {
    $this->hasMore = true;
  }


  public function hasMore(): bool {
    return $this->hasMore;
  }


  public function addFailure( string $failureCode ): void {
    if ( ! in_array( $failureCode, $this->failureCodes, true ) ) {
      $this->failureCodes[] = $failureCode;
    }
  }


  public function hasFailures(): bool {
    return ! empty( $this->failureCodes );
  }


  public function getFailureCodes(): array {
    return $this->failureCodes;
  }


  public function addRelease( ReleaseSummary $summary ): void {
    $this->releasedJobs           += $summary->getReleasedJobs();
    $this->releasedWords          += $summary->getReleasedWords();
    $this->unconfirmedReleaseJobs += $summary->getUnconfirmedJobs();
  }


  public function getRelease(): ReleaseSummary {
    return new ReleaseSummary(
      $this->releasedJobs,
      $this->releasedWords,
      $this->unconfirmedReleaseJobs
    );
  }


}
