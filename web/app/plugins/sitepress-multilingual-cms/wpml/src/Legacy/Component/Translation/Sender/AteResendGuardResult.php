<?php

namespace WPML\Legacy\Component\Translation\Sender;

use WPML\Core\Component\Translation\Domain\TranslationBatch\TranslationBatch;

final class AteResendGuardResult {

  private $batch;

  private $reportedJobIds;

  private $abortText;

  private $reportedStringIds;

  private $sendAnywayElements;


  public function __construct(
    TranslationBatch $batch,
    array $reportedJobIds = [],
    ?string $abortText = null,
    array $reportedStringIds = [],
    array $sendAnywayElements = []
  ) {
    $this->sendAnywayElements = $sendAnywayElements;
    $this->batch          = $batch;
    $this->reportedJobIds = array_values( array_unique( array_map( 'intval', $reportedJobIds ) ) );
    $this->abortText      = $abortText;

    $this->reportedStringIds = [];
    foreach ( $reportedStringIds as $jobId => $stringIds ) {
      $this->reportedStringIds[ $jobId ] = array_values( array_unique( array_map( 'intval', $stringIds ) ) );
    }
  }


  public function getBatch(): TranslationBatch {
    return $this->batch;
  }


  public function getReportedJobIds(): array {
    return $this->reportedJobIds;
  }


  public function getReportedStringIds(): array {
    return $this->reportedStringIds;
  }


  public function getAbortText() {
    return $this->abortText;
  }


  public function getSendAnywayElements(): array {
    return $this->sendAnywayElements;
  }


}
