<?php

namespace WPML\Core\Component\Translation\Application\Service;

use WPML\Core\Component\Translation\Application\Query\AutomaticTranslationSupportQueryInterface;
use WPML\Core\Component\Translation\Application\Query\TranslationQueryInterface;
use WPML\Core\Component\Translation\Application\Query\UnsolvableJobsQueryInterface;
use WPML\Core\Component\Translation\Application\Repository\TermBundleMembersInterface;
use WPML\Core\Component\Translation\Application\Service\ResendUnsolvableJobs\SendToTranslationDtoCollectionBuilder;
use WPML\Core\Component\Translation\Application\Service\ResendUnsolvableJobs\TimestampBatchNameGenerator;
use WPML\Core\Component\Translation\Application\Service\TranslationService\TranslationServiceException;
use WPML\Core\Component\Translation\Domain\Translation;
use WPML\Core\Component\Translation\Domain\TranslationType;
use WPML\Core\SharedKernel\Component\Translation\Domain\PackageOwnerInterface;
use WPML\PHP\Exception\Exception;
use WPML\PHP\Exception\InvalidArgumentException;
use WPML\PHP\Exception\InvalidItemIdException;

class ResendUnsolvableJobsService {

  const CANCEL_SOURCE = 'resend';

  private $translationQuery;

  private $cancelJobsService;

  private $translationService;

  private $dtoCollectionBuilder;

  private $batchNameGenerator;

  private $unsolvableJobsQuery;

  private $userSendFlight;

  private $owners;

  private $ownerEvents = [];

  private $supportQuery;

  private $bundleMembers;


  public function __construct(
    TranslationQueryInterface $translationQuery,
    CancelJobsService $cancelJobsService,
    TranslationService $translationService,
    SendToTranslationDtoCollectionBuilder $dtoCollectionBuilder,
    TimestampBatchNameGenerator $batchNameGenerator,
    UnsolvableJobsQueryInterface $unsolvableJobsQuery,
    UserSendFlightInterface $userSendFlight,
    PackageOwnerInterface $owners,
    AutomaticTranslationSupportQueryInterface $supportQuery,
    TermBundleMembersInterface $bundleMembers
  ) {
    $this->translationQuery     = $translationQuery;
    $this->cancelJobsService    = $cancelJobsService;
    $this->translationService   = $translationService;
    $this->dtoCollectionBuilder = $dtoCollectionBuilder;
    $this->batchNameGenerator   = $batchNameGenerator;
    $this->unsolvableJobsQuery  = $unsolvableJobsQuery;
    $this->userSendFlight       = $userSendFlight;
    $this->owners               = $owners;
    $this->supportQuery         = $supportQuery;
    $this->bundleMembers        = $bundleMembers;
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

    $refusedPackages    = [];
    $resendTranslations = [];
    $bundles = [];
    foreach ( $translations as $translation ) {
      $type      = $translation->getType();
      $packageId = $translation->getOriginalElementId();
      $job       = $translation->getJob();
      if ( $type && TranslationType::TERM_BUNDLE === $type->get() && $job ) {
        $members = $this->bundleMembers->unsolved( $job->getId() );
        if ( $members ) {
          $bundles[ $job->getId() ] = $members;
          foreach ( $members as $member ) {
            $resendTranslations[] = new Translation(
              $member['translationId'],
              $translation->getStatus(),
              TranslationType::taxonomy(),
              $member['termTaxonomyId'],
              $translation->getSourceLanguageCode(),
              $translation->getTargetLanguageCode(),
              $job
            );
          }
        }
        continue;
      }
      $ridesOwner = $type && TranslationType::PACKAGE === $type->get()
        && $this->owners->ridesOwner( $packageId );
      if ( $ridesOwner ) {
        $job   = $translation->getJob();
        $owner = $this->owners->getOwner( $packageId );
        if ( ! isset( $refusedPackages[ $packageId ] ) ) {
          $refusedPackages[ $packageId ] = [
            'jobId'     => $job ? $job->getId() : null,
            'packageId' => $packageId,
            'reason'    => 'package_rides_owner',
            'owner'     => $owner,
            'message'   => $this->owners->message( $owner ),
          ];
          if ( is_array( $owner ) ) {
            $ownerKey = $owner['element_type'] . ':' . $owner['element_id'];
            if ( empty( $this->ownerEvents[ $ownerKey ] ) ) {
              $this->ownerEvents[ $ownerKey ] = true;
              $this->owners->changed( $owner, $packageId );
            }
          }
        }
        continue;
      }
      $resendTranslations[] = $translation;
    }

    $batchName = $batchName ?: $this->batchNameGenerator->generate();
    $groups    = $resendTranslations
      ? $this->dtoCollectionBuilder->buildGroups( $batchName, $resendTranslations )
      : [];

    $cancelBeforeSend = array_values( array_diff( $jobIds, array_keys( $bundles ) ) );
    $groupJobIds      = array_merge( [], ...array_column( $groups, 'jobIds' ) );
    $cancelFirst      = array_values( array_diff( $cancelBeforeSend, $groupJobIds ) );

    list( $results, $createdTranslationIds ) = $this->userSendFlight->run(
      function () use ( $cancelBeforeSend, $cancelFirst, $groups, $bundles ) {
        if ( $cancelFirst ) {
          $this->cancelJobsService->cancelJobs( $cancelFirst, false, self::CANCEL_SOURCE );
        }

        $results               = [];
        $createdTranslationIds = [];
        foreach ( $groups as $key => $group ) {
          $groupBundles = array_intersect_key( $bundles, array_flip( $group['jobIds'] ) );
          $this->leaveOldBundles( $groupBundles );

          try {
            $cancel = array_values( array_intersect( $cancelBeforeSend, $group['jobIds'] ) );
            if ( $cancel ) {
              $this->cancelJobsService->cancelJobs( $cancel, false, self::CANCEL_SOURCE );
            }

            $result = $this->translationService->send( $group['dto'] );
          } catch ( \Throwable $e ) {
            foreach ( $groupBundles as $bundleJobId => $members ) {
              $this->restoreMembers( $bundleJobId, $members, [] );
            }
            throw $e;
          }

          $results[ $key ] = $result->toArray();
          foreach ( $result->getCreatedTranslations() as $createdTranslation ) {
            $createdTranslationIds[ $createdTranslation->getId() ] = true;
          }

          $this->finishOldBundles( $groupBundles, $createdTranslationIds );
        }

        return [ $results, $createdTranslationIds ];
      }
    );

    $resentJobIds = $this->resentJobIds( $resendTranslations, $createdTranslationIds );

    return [
      'batchName'       => $batchName,
      'results'         => $results,
      'resentJobIds'    => $resentJobIds,
      'notResentJobIds' => array_values( array_diff( $requestedJobIds, $resentJobIds ) ),
      'refusedPackages' => array_values( $refusedPackages ),
      'unsupportedPairs' => $this->unsupportedPairs( $translations, $resentJobIds ),
    ];
  }


  private function leaveOldBundles( array $bundles ): void {
    foreach ( $bundles as $bundleJobId => $members ) {
      $rids = array_column( $members, 'termRid' );
      $this->bundleMembers->releaseWords( $bundleJobId, $rids );
      $this->bundleMembers->setState( $bundleJobId, $rids, TermBundleMembersInterface::STATE_DROPPED );
    }
  }


  private function finishOldBundles( array $bundles, array $createdTranslationIds ): void {
    foreach ( $bundles as $bundleJobId => $members ) {
      $this->restoreMembers( $bundleJobId, $members, $createdTranslationIds );
      if ( ! $this->anyCreated( $members, $createdTranslationIds ) ) {
        continue;
      }
      if ( $this->bundleMembers->hasApplied( $bundleJobId ) ) {
        $this->bundleMembers->completeDelivered( $bundleJobId );
      } else {
        $this->cancelJobsService->cancelJobs( [ $bundleJobId ], false, self::CANCEL_SOURCE );
      }
    }
  }


  private function restoreMembers( int $bundleJobId, array $members, array $createdTranslationIds ) {
    $ridsByState = [];
    foreach ( $members as $member ) {
      if ( ! isset( $createdTranslationIds[ $member['translationId'] ] ) ) {
        $ridsByState[ $member['state'] ][] = $member['termRid'];
      }
    }
    foreach ( $ridsByState as $state => $rids ) {
      $this->bundleMembers->setState( $bundleJobId, $rids, $state );
    }
  }


  private function anyCreated( array $members, array $createdTranslationIds ): bool {
    foreach ( $members as $member ) {
      if ( isset( $createdTranslationIds[ $member['translationId'] ] ) ) {
        return true;
      }
    }

    return false;
  }


  private function unsupportedPairs( array $translations, array $resentJobIds ): array {
    $pairs           = [];
    $supportBySource = [];
    foreach ( $translations as $translation ) {
      $job = $translation->getJob();
      if ( ! $job || in_array( $job->getId(), $resentJobIds, true ) ) {
        continue;
      }

      $source = $translation->getSourceLanguageCode();
      $target = $translation->getTargetLanguageCode();
      if ( ! isset( $supportBySource[ $source ] ) ) {
        $supportBySource[ $source ] = $this->supportQuery->supportFrom( $source );
      }

      if ( isset( $supportBySource[ $source ][ $target ] ) && false === $supportBySource[ $source ][ $target ]['supported'] ) {
        $pairs[] = [
          'jobId'              => $job->getId(),
          'sourceLanguageCode' => $source,
          'targetLanguageCode' => $target,
        ];
      }
    }

    return $pairs;
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
