<?php
namespace WPML\Core\Component\Translation\Application\Service;

use WPML\Core\Component\Translation\Domain\Entity\JobError;
use WPML\Core\Component\Translation\Domain\Repository\JobErrorRepositoryInterface;

class TranslateJobErrorService {

  private $repository;


  public function __construct( JobErrorRepositoryInterface $repository ) {
    $this->repository = $repository;
  }


  public function logError(
    int $jobId,
    int $ateJobId,
    string $errorType,
    string $errorMessage,
    array $errorData = []
  ) {
    $existing = $this->repository->findByJobId( $jobId );

    if ( $existing ) {
      if ( $existing->getErrorType() === $errorType && $existing->getErrorMessage() === $errorMessage ) {
        $this->repository->incrementCounter( $jobId );

        return new JobError(
          $existing->getJobId(),
          $existing->getAteJobId(),
          $existing->getErrorType(),
          $existing->getErrorMessage(),
          $existing->getErrorData(),
          $existing->getCounter() + 1
        );
      }

      $this->repository->delete( $jobId );
    }

    $jobError = new JobError(
      $jobId,
      $ateJobId,
      $errorType,
      $errorMessage,
      $errorData
    );

    $this->repository->insert( $jobError );

    return $jobError;
  }


  public function deleteError( int $jobId ): int {
    return $this->repository->delete( $jobId );
  }


  public function recordDiscardedSourceMd5( int $jobId, string $md5 ): void {
    $this->repository->mergeErrorData( $jobId, [ JobError::ERROR_DATA_SOURCE_MD5 => $md5 ] );
  }


  public function recordDiscardedMemberSourceMd5s( int $jobId, array $md5s ): void {
    $this->repository->mergeErrorData(
      $jobId,
      [
        JobError::ERROR_DATA_MEMBER_SOURCE_MD5 => array_map(
          function ( string $md5 ): array {
            return [ JobError::ERROR_DATA_SOURCE_MD5 => $md5 ];
          },
          array_values( $md5s )
        ),
      ]
    );
  }


  public function getCount(): int {
    return $this->repository->count();
  }


}
