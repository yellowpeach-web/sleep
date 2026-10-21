<?php

namespace WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\CancelJobs;

use WPML\Core\Component\Translation\Application\Query\TranslationQueryInterface;
use WPML\Core\Component\Translation\Application\Query\UnsolvableJobsQueryInterface;
use WPML\Core\Component\Translation\Application\Service\AutomaticJobsCancellation\ReleaseLedger;
use WPML\Core\Component\Translation\Application\Service\CancelJobsService;
use WPML\Core\Component\Translation\Application\Service\UnsolvableJobIdsSnapshotInterface;
use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus;
use WPML\PHP\Exception\InvalidArgumentException;

class CancelJobsController implements EndpointInterface {

    private $cancelJobsService;

    private $unsolvableJobsQuery;

    private $unsolvableJobIdsSnapshot;

    private $translationQuery;


  public function __construct(
    CancelJobsService $cancelJobsService,
    UnsolvableJobsQueryInterface $unsolvableJobsQuery,
    UnsolvableJobIdsSnapshotInterface $unsolvableJobIdsSnapshot,
    TranslationQueryInterface $translationQuery
  ) {
      $this->cancelJobsService        = $cancelJobsService;
      $this->unsolvableJobsQuery      = $unsolvableJobsQuery;
      $this->unsolvableJobIdsSnapshot = $unsolvableJobIdsSnapshot;
      $this->translationQuery         = $translationQuery;
  }


  public function handle( $requestData = null ): array {
      $requestData = $requestData ?: [];

      $jobIds = $requestData['jobIds'] ?? null;

    if ( $jobIds === null ) {
        throw new InvalidArgumentException( 'jobIds array is required.' );
    }

    if ( ! is_array( $jobIds ) ) {
        throw new InvalidArgumentException( 'jobIds must be an array.' );
    }

      list( $listedJobIds, $notListedJobIds ) = $this->splitByListing( $jobIds );

      $releaseMark = ReleaseLedger::instance()->mark();

      $result = $this->cancelJobsService->cancelJobs( $listedJobIds, true );

    if ( $notListedJobIds ) {
        $this->unsolvableJobIdsSnapshot->forget( $notListedJobIds );

      foreach ( $notListedJobIds as $jobId ) {
          $result['cancelledJobIds'][] = $jobId;

          $status = $this->currentStatus( $jobId );
        if ( $status !== null ) {
            $result['restoredStatuses'][ $jobId ] = $status;
        }
      }
    }

      $result['release'] = ReleaseLedger::instance()->summaryFrom( $releaseMark )->toArray();

      return [
          'success' => true,
          'data'    => $result,
      ];
  }


  private function splitByListing( array $jobIds ): array {
      $listed = [];
    foreach ( $this->unsolvableJobsQuery->getUnsolvableJobs() as $row ) {
        $listed[ $row['jobId'] ] = true;
    }

      $listedJobIds    = [];
      $notListedJobIds = [];

    foreach ( $jobIds as $jobId ) {
      if ( ! is_numeric( $jobId ) || (int) $jobId < 1 ) {
          continue;
      }

        $jobId = (int) $jobId;

      if ( isset( $listed[ $jobId ] ) ) {
          $listedJobIds[] = $jobId;
      } else {
          $notListedJobIds[] = $jobId;
      }
    }

      return [ $listedJobIds, array_values( array_unique( $notListedJobIds ) ) ];
  }


  private function currentStatus( int $jobId ) {
      $translation = $this->translationQuery->getOneByJobId( $jobId );

    if ( ! $translation ) {
        return null;
    }

      $status = $translation->getStatus()->get();

    if ( TranslationStatus::COMPLETE === $status && $translation->needsUpdate() ) {
        return TranslationStatus::NEEDS_UPDATE;
    }

      return $status;
  }


}
