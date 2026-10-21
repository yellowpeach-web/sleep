<?php

namespace WPML\Core\Component\Translation\Domain\Entity;

class JobError {

  const TYPE_SYNC     = 'SyncError';
  const TYPE_DOWNLOAD = 'DownloadError';
  const TYPE_APPLY    = 'ApplyError';

  const ERROR_DATA_SOURCE_MD5 = 'source_md5';

  const RETRY_LIMITS = [
    self::TYPE_SYNC     => 1,
    self::TYPE_DOWNLOAD => 3,
    self::TYPE_APPLY    => 3,
  ];


  private $jobId;

  private $ateJobId;

  private $errorType;

  private $errorMessage;

  private $errorData;

  private $counter;


  public function __construct(
    int $jobId,
    int $ateJobId,
    string $errorType,
    string $errorMessage,
    array $errorData = [],
    int $counter = 1
  ) {
    $this->jobId = $jobId;
    $this->ateJobId = $ateJobId;
    $this->errorType = $errorType;
    $this->errorMessage = $errorMessage;
    $this->errorData = $errorData;
    $this->counter = $counter;
  }


  public function getJobId(): int {
    return $this->jobId;
  }


  public function getAteJobId(): int {
    return $this->ateJobId;
  }


  public function getErrorType(): string {
    return $this->errorType;
  }


  public function getErrorMessage(): string {
    return $this->errorMessage;
  }


  public function getErrorData(): array {
    return $this->errorData;
  }


  public function isBeyondRetryLimit(): bool {
    if ( ! isset( self::RETRY_LIMITS[ $this->errorType ] ) ) {
      return false;
    }

    return $this->counter >= self::RETRY_LIMITS[ $this->errorType ];
  }


  public function getCounter(): int {
    return $this->counter;
  }


}
