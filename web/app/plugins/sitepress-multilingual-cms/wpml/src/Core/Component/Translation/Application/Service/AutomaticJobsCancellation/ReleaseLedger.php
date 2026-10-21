<?php

namespace WPML\Core\Component\Translation\Application\Service\AutomaticJobsCancellation;

final class ReleaseLedger {

  private static $instance = null;

  private $entries = [];


  private function __construct() {
  }


  public static function instance(): self {
    if ( null === self::$instance ) {
      self::$instance = new self();
    }

    return self::$instance;
  }


  public static function reset(): void {
    self::$instance = null;
  }


  public function mark(): int {
    return count( $this->entries );
  }


  public function recordReleased( int $ateJobId, int $words, ?string $ledgerId = null ): void {
    $this->entries[] = [
      'ate_job_id' => $ateJobId,
      'words'      => max( 0, $words ),
      'ledger_id'  => $ledgerId,
      'released'   => true,
    ];
  }


  public function recordUnconfirmed( int $ateJobId ): void {
    $this->entries[] = [
      'ate_job_id' => $ateJobId,
      'words'      => 0,
      'ledger_id'  => null,
      'released'   => false,
    ];
  }


  public function summary(): ReleaseSummary {
    return $this->summaryFrom( 0 );
  }


  public function summaryFrom( int $mark ): ReleaseSummary {
    $releasedJobs    = 0;
    $releasedWords   = 0;
    $unconfirmedJobs = 0;

    foreach ( array_slice( $this->entries, max( 0, $mark ) ) as $entry ) {
      if ( $entry['released'] ) {
        $releasedJobs ++;
        $releasedWords += $entry['words'];
      } else {
        $unconfirmedJobs ++;
      }
    }

    return new ReleaseSummary( $releasedJobs, $releasedWords, $unconfirmedJobs );
  }

}
