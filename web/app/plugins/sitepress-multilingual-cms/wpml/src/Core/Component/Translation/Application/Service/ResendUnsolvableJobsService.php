<?php

namespace WPML\Core\Component\Translation\Application\Service;

use WPML\Core\Component\Translation\Application\Query\TranslationQueryInterface;
use WPML\Core\Component\Translation\Application\Query\UnsolvableJobsQueryInterface;
use WPML\Core\Component\Translation\Application\Service\ResendUnsolvableJobs\SendToTranslationDtoCollectionBuilder;
use WPML\Core\Component\Translation\Application\Service\ResendUnsolvableJobs\TimestampBatchNameGenerator;
use WPML\Core\Component\Translation\Application\Service\TranslationService\TranslationServiceException;
use WPML\PHP\Exception\Exception;
use WPML\PHP\Exception\InvalidArgumentException;
use WPML\PHP\Exception\InvalidItemIdException;

class ResendUnsolvableJobsService {

  private $translationQuery;

  private $cancelJobsService;

  private $translationService;

  private $dtoCollectionBuilder;

  private $batchNameGenerator;

  private $unsolvableJobsQuery;

  private $userSendFlight;


  public function __construct(
    TranslationQueryInterface $translationQuery,
    CancelJobsService $cancelJobsService,
    TranslationService $translationService,
    SendToTranslationDtoCollectionBuilder $dtoCollectionBuilder,
    TimestampBatchNameGenerator $batchNameGenerator,
    UnsolvableJobsQueryInterface $unsolvableJobsQuery,
    UserSendFlightInterface $userSendFlight
  ) {
    $this->translationQuery     = $translationQuery;
    $this->cancelJobsService    = $cancelJobsService;
    $this->translationService   = $translationService;
    $this->dtoCollectionBuilder = $dtoCollectionBuilder;
    $this->batchNameGenerator   = $batchNameGenerator;
    $this->unsolvableJobsQuery  = $unsolvableJobsQuery;
    $this->userSendFlight       = $userSendFlight;
  }


  private function keepResendableJobIds( array $jobIds ): array {
    $resendable = [];
    foreach ( $this->unsolvableJobsQuery->getUnsolvableJobs() as $job ) {
      if ( ! empty( $job['canResend'] ) ) {
        $resendable[] = $job['jobId'];
      }
    }

    return array_values( array_intersect( array_map( 'intval', $jobIds ), $resendable ) );
  }


  public function resend( array $jobIds, $batchName = null ): array {
    $requestedCount  = count( $jobIds );
    $requestedJobIds = array_values( array_unique( array_map( 'intval', $jobIds ) ) );
    $jobIds          = $this->keepResendableJobIds( $requestedJobIds );

    if ( empty( $jobIds ) ) {
      throw new InvalidArgumentException(
        $requestedCount > 0
          ? 'None of the requested jobs can be resent. The list they came from is out of date; reload it to see which jobs can still be recovered.'
          : 'No translations found for the provided job IDs.'
      );
    }

    $translations = $this->translationQuery->getManyByJobIds( $jobIds );
    if ( empty( $translations ) ) {
      throw new InvalidArgumentException( 'No translations found for the provided job IDs.' );
    }

    $batchName = $batchName ?: $this->batchNameGenerator->generate();
    $dtos      = $this->dtoCollectionBuilder->buildCollection( $batchName, $translations );

    list( $results, $createdTranslationIds ) = $this->userSendFlight->run(
      function () use ( $jobIds, $dtos ) {
        $this->cancelJobsService->cancelJobs( $jobIds );

        $results               = [];
        $createdTranslationIds = [];
        foreach ( $dtos as $sourceLanguage => $dto ) {
          $result                     = $this->translationService->send( $dto );
          $results[ $sourceLanguage ] = $result->toArray();
          foreach ( $result->getCreatedTranslations() as $createdTranslation ) {
            $createdTranslationIds[ $createdTranslation->getId() ] = true;
          }
        }

        return [ $results, $createdTranslationIds ];
      }
    );

    $resentJobIds = $this->resentJobIds( $translations, $createdTranslationIds );

    return [
      'batchName'       => $batchName,
      'results'         => $results,
      'resentJobIds'    => $resentJobIds,
      'notResentJobIds' => array_values( array_diff( $requestedJobIds, $resentJobIds ) ),
    ];
  }


  private function resentJobIds( array $translations, array $createdTranslationIds ): array {
    $resent = [];
    foreach ( $translations as $translation ) {
      $job = $translation->getJob();
      if ( $job && isset( $createdTranslationIds[ $translation->getId() ] ) ) {
        $resent[] = $job->getId();
      }
    }

    return array_values( array_unique( $resent ) );
  }


}
